# Rider

Мультиорганизационный обмен лидами. Бизнес-схема в `BUSINESS.md`, состав и домены в `PLAN.md`, код в `CONVENTIONS.md`, закрытый стенд на отдельной тачке в `INFRA.md`. Как открыть этот стенд с другой машины: `STAGE.md`.

Локально имена `*.rider.io`, `*.chase.io` и `*.marshall.io` открываются по HTTPS. Единственный вход на 80 и 443 — Traefik. API, кабинет, Kratos и Kafka UI свои порты наружу не публикуют: Traefik выбирает контейнер по заголовку `Host`.

## Локальный Traefik

Прокси общий со стендом Lead Market и живёт в каталоге `meta`, рядом с этим проектом: файл `compose.yaml`, сервис `leadmarket-proxy`. Образ `traefik:v3.6.25`. Провайдер Docker смотрит сокет и сеть `dev-leadmarket-network`, файловый провайдер читает `/etc/traefik/dynamic`. Вход `http` слушает 80, вход `https` слушает 443.

Сеть одна на все локальные compose Rider. Если её ещё нет:

```bash
docker network create dev-leadmarket-network
```

Сертификаты выпускает mkcert, по одному вайлдкарду на апекс. Бинарь лежит в `meta/cert/mkcert`, файлы сертификатов тоже в `meta/cert`. Корневой CA mkcert стоит в `~/.local/share/mkcert` и добавлен в доверенные системы и браузера. Traefik берёт их из `meta/dynamic/rider-tls.yml`:

- `*.rider.io`
- `*.chase.io`
- `*.marshall.io`

Один сертификат покрывает один уровень (`id.chase.io`, `buyer.chase.io`, `api.chase.io`). Имя `a.b.chase.io` он не покрывает.

Поднять прокси и локальный DNS:

```bash
cd /home/jeny/dev/meta
docker compose up -d leadmarket-proxy rider-dns
```

`rider-dns` — dnsmasq в сети хоста. Он отвечает на `rider.io`, `chase.io`, `marshall.io` и все их поддомены адресом `127.0.0.1`, остальные запросы отдаёт дальше. Chrome с Secure DNS этот dnsmasq не спрашивает, поэтому в `/etc/hosts` нужны явные имена:

```text
127.0.0.1 id.rider.io id.chase.io id.marshall.io owner.rider.io api.rider.io api.chase.io api.marshall.io buyer.chase.io seller.chase.io buyer.marshall.io seller.marshall.io kafka.rider.io
```

После этого поднимаются сервисы Rider, все на сети `dev-leadmarket-network`:

```bash
docker compose -f infra/compose.yaml up -d
docker compose -f kratos/compose.yaml up -d
docker compose -f api/compose.yaml up -d
docker compose -f dashboard/compose.yaml up -d
```
