# Shelf age zones & pricing

План внедрения возрастных зон склада, скидок по зонам и поведения **Live** для day orders.  
Статус: **согласовано**, реализация — по фазам ниже.

Связанный контекст: `Warehouse`, `LeadRepository::stockFreshnessForPreset`, `StockFulfillment`, `OrderDesk`, partner Buy Leads (вкладки Stock / Day orders).

---

## Цели

1. Админ организации задаёт **до 4 зон** возраста лида на полке (по `created_at`).
2. **Live** — не продаётся со склада, не в Quantity / freshness bar; резерв под day orders.
3. **Fresh / Good / Expired** — только со склада, со **скидкой** от `preset.basePriceCents`.
4. Партнёр в модалке покупки видит **разбивку** по зонам и итоговую сумму.
5. **Quote** и **fulfillment** используют **один и тот же** алгоритм отбора лидов.
6. **Order cost** в кабинете = **сумма** `effectivePrice` по проданным лидам, не `unit × qty` и не средневзвешенная ставка в заказе.

---

## Принятые решения

| Тема | Решение |
|------|---------|
| Хранение policy | **JSON** на `Organization` (аналог markup) |
| Число зон | Ровно **4**, больше не разрешаем |
| Горизонт шкалы | Макс. **48h**; лиды **старше 48h** попадают в **Expired** |
| Порядок выдачи со склада | **Expired → Good → Fresh** (разгрузка полки), внутри зоны — **старше первыми** (FIFO по age) |
| Live в stock buy | **Не участвует** |
| `Order.priceCents` | Для stock-mixed **не** единая unit-цена; **Order cost** = `SUM(lead.priceCents)` по sold/placements. Опционально поле заказа `reservedTotalCents` при open для hold баланса |
| Effective price | На каждом лиде при `sell()`: `priceCents` = effective; база пресета остаётся в preset / отдельном поле при необходимости отчётов |
| Quote ↔ fulfill | Общий сервис `StockPickPlan` (или аналог): один SQL + slice, quote и `StockFulfillment` вызывают его |
| Live + day order | При **открытии day order**: если есть **live** лиды под пресет — **не** мгновенная продажа; **планировщик** как LM-injector: слоты например **+5m, +10m, +15m** от `now`, к каждому **±1 min** jitter; по слоту — assign/sell в заказ |

### Зоны (семантика)

| # | ID | На полке | Скидка | Назначение |
|---|-----|----------|--------|------------|
| 1 | `live` | Нет | **0%** (фикс) | Резерв под day orders; виден только планировщику / админу |
| 2 | `fresh` | Да | > 0, настраивается | Пример: 10% |
| 3 | `good` | Да | > 0 | Пример: 30% |
| 4 | `expired` | Да | > 0 | Пример: 70%; всё **> horizon** тоже сюда |

Границы — в **минутах** от `created_at`: `[0, t1)`, `[t1, t2)`, `[t2, t3)`, `[t3, ∞)` где `t3 ≤ horizonMinutes` (48h = 2880).

**Дефолт policy** (миграция, обсуждаемо): `0–30m` live, `30m–2h` fresh 10%, `2h–6h` good 30%, `6h–48h+` expired 70%.

---

## JSON schema (`organization.shelfAgePolicy`)

```json
{
  "horizonMinutes": 2880,
  "zones": [
    {
      "id": "live",
      "label": "Live",
      "startMinutes": 0,
      "endMinutes": 30,
      "discountPercent": 0,
      "sellableOnShelf": false
    },
    {
      "id": "fresh",
      "label": "Fresh",
      "startMinutes": 30,
      "endMinutes": 120,
      "discountPercent": 10,
      "sellableOnShelf": true
    },
    {
      "id": "good",
      "label": "Good",
      "startMinutes": 120,
      "endMinutes": 360,
      "discountPercent": 30,
      "sellableOnShelf": true
    },
    {
      "id": "expired",
      "label": "Expired",
      "startMinutes": 360,
      "endMinutes": null,
      "discountPercent": 70,
      "sellableOnShelf": true
    }
  ]
}
```

`endMinutes: null` = до бесконечности (все лиды старше `startMinutes`, включая > 48h).

**Валидация:** 4 зоны, порядок id фиксированный, интервалы без дыр и пересечений, `live.discountPercent === 0`, `!live.sellableOnShelf`, остальные `sellableOnShelf === true` и `discountPercent >= 1`.

---

## Доменные сервисы (API)

### `ShelfAgePolicyResolver`

- `zoneForLead(createdAt, policy, now): Zone`
- `effectivePriceCents(basePriceCents, zone): int`
- `isSellableOnShelf(zone): bool`

### `StockPickPlan`

Вход: `organizationId`, `preset`, `qty`, `leadGender?`, `policy`, `now`.

Выход:

- `lines[]`: `{ zoneId, count, unitPriceCents, discountPercent }`
- `leadUuids[]` — упорядоченный список (тот же порядок, что при fulfill)
- `totalCents`

**Алгоритм отбора:**

1. Только `in_stock`, матч пресета, **возраст ≥ live.end** (или не в live-интервале).
2. Сортировка: `zone_rank` (expired=0, good=1, fresh=2), затем `created_at ASC` (старше первыми внутри зоны).
3. Взять первые `qty` лидов; сгруппировать для quote.

Quote endpoint и `StockFulfillment::fill` **оба** вызывают `StockPickPlan` (fulfill — с блокировкой/повторной проверкой наличия).

### `Warehouse::inventory`

- `available` — count **sellable** only (без live).
- `stockShelfMix` — счётчики по **fresh / good / expired** (не live).
- Партнёрам отдавать `shelfAgePolicy` (read-only) для легенды и цветов UI.

### Order cost (все типы заказов)

- **Единое правило:** стоимость заказа для UI и биллинга = **сумма** `priceCents` проданных лидов (placements / sold leads).
- Day order с одной ставкой: каждый лид по-прежнему `sell(..., orderPrice)` — сумма совпадает с `volume × unit` если unit константа.
- Stock order со скидками: сумма **разная** по лидам — Order cost пересчитывается из лидов, **не** из `Order.priceCents × supplied`.
- При **open** stock order: `assertPartnerCanAfford(quote.totalCents)` по результату `StockPickPlan` на момент open (или reservedTotal).

### Live injector (day orders)

При `OrderDesk::open` для **не-stock** заказа:

1. Найти live-лиды под пресет (в интервале live зоны).
2. Для каждого (или пакетами по N) создать **scheduled fulfillment**:
   - Базовые задержки: 5, 10, 15, … минут (правило распределения — зафиксировать: 1 лид = ближайший слот, или round-robin по слотам).
   - `executeAt = baseDelay + random(-60s, +60s)`.
3. Worker/cron выполняет slot → `assign` lead в order по **base price** (live без скидки).
4. Не использовать тот же моментальный путь, что stock buy (анти-палево «склад мгновенно»).

*Детали очереди: переиспользовать паттерн LM injector / `OrderPool` — уточнить при реализации фазы E.*

---

## Admin UI (dashboard)

- Экран в настройках организации: **Shelf age & discounts**.
- Горизонт max 48h; **multi-thumb slider** — 3 границы между 4 зонами.
- Под сегментом: подпись времени + **% скидки** (live — disabled 0).
- Preview: effective price при example base $1.00.
- API: `GET/PATCH /organization/shelf-age-policy`.

---

## Partner UI (dashboard)

- **Stock tab:** Quantity и freshness bar **без live**; сегменты = fresh/good/expired; hint из policy (+ скидки).
- **Price column:** base + опционально «from $X» (max discount).
- **StockBuyDialog:** quote → таблица строк по зонам, total, confirm.
- **My orders — Order cost:** для stock и mixed — **SUM(leads)** (бэкенд поле или агрегация на read).

---

## Фазы реализации

| Фаза | Содержание | DoD |
|------|------------|-----|
| **A** | JSON policy, migration + defaults, resolver, admin API + slider UI | Админ сохраняет зоны |
| **B** | CH queries: sellable count, shelf mix, exclude live; inventory API | Каталог и бар честные |
| **C** | `StockPickPlan`, quote endpoint, buy modal | Партнёр видит разбивку |
| **D** | StockFulfillment + wallet hold/charge по total; lead `priceCents`; Order cost из SUM | Деньги сходятся с quote |
| **E** | Live injector при open day order; worker слотов | Live уходит в заказ с задержкой и jitter |
| **F** | Order list cost column; tests; feature flag | Регрессии закрыты |

Рекомендуемый порядок: **A → B → C → D**, параллельно или следом **E**, затем **F**.

---

## Тесты (минимум)

- Policy validation (дыры, live скидка, 4 зоны).
- Resolver: границы минут, > 48h → expired.
- `StockPickPlan`: порядок expired→good→fresh, quote qty = fulfill qty при стабильном складе.
- Effective price rounding (cents).
- Open stock order: afford check = quote total.

---

## Затронутые компоненты

| Область | Файлы / модули |
|---------|----------------|
| API | `Organization`, `OrganizationDesk`, `LeadRepository`, `Warehouse`, `OrderDesk`, `StockFulfillment`, `MarketController`, новый `ShelfAgePolicy*`, `StockPickPlan`, live scheduler |
| Dashboard | org settings, `Stock.vue`, `StockFreshnessBar`, `stockFreshness.ts`, `StockBuyDialog`, `Orders.vue` (order cost), `market.ts` |
| Workers | injector worker / расширение matcher / order-pool (фаза E) |

---

## Открыто на реализацию (мелочь)

- Точное распределение live-лидов по слотам 5/10/15m при `volume > 1`.
- Нужно ли партнёру показывать «N leads reserved for day orders» (live count) — пока **нет**.

---

*Документ создан: 2026-11-23. Обновлять по мере реализации фаз.*