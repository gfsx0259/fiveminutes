# Стенд с чистой машины

Стенд уже запущен на `2.29.205.3`. Доступы в [ACCESS.md](ACCESS.md) и в таблице внизу.

Сертификат стенда выписан mkcert и в публичный список доверенных не входит. Корневой CA лежит в репозитории: `traefik/cert/rootCA.pem`. Это дев-сертификат, им подписаны `*.rider.io`, `*.chase.io` и `*.marshall.io`. Новый mkcert на своей машине выпускать не надо: у него будет другой корень, и браузер стенду не поверит.

## Имена

В `/etc/hosts` нет масок, поэтому каждое имя пишется отдельно. Строка одна:

```text
2.29.205.3 id.rider.io id.chase.io id.marshall.io owner.rider.io api.rider.io api.chase.io api.marshall.io buyer.chase.io seller.chase.io admin.chase.io buyer.marshall.io seller.marshall.io admin.marshall.io kafka.rider.io
```

На Linux и macOS файл `/etc/hosts`, правка через `sudo`. На Windows это `C:\Windows\System32\drivers\etc\hosts`, блокнот от имени администратора.

Если эти имена уже смотрят на `127.0.0.1`, замени ту строку. Вторая строка с тем же именем не перебивает первую.

Проверка:

```bash
getent hosts api.marshall.io buyer.marshall.io
```

Оба имени должны показать `2.29.205.3`.

## Сертификат

Команды из корня клонированного репозитория.

Linux:

```bash
sudo cp traefik/cert/rootCA.pem /usr/local/share/ca-certificates/rider-dev.crt
sudo update-ca-certificates
```

Chrome на Linux дополнительно читает своё хранилище. Пакет `libnss3-tools` даёт `certutil`:

```bash
mkdir -p "$HOME/.pki/nssdb"
certutil -d sql:"$HOME/.pki/nssdb" -A -t "C,," -n "rider-dev" -i traefik/cert/rootCA.pem
```

После этого браузер нужно полностью закрыть и открыть снова.

macOS:

```bash
sudo security add-trusted-cert -d -r trustRoot -k /Library/Keychains/System.keychain traefik/cert/rootCA.pem
```

Windows, из PowerShell от имени администратора:

```powershell
certutil -addstore -f "ROOT" traefik\cert\rootCA.pem
```

Firefox держит своё хранилище. В нём: Настройки, Приватность и защита, Сертификаты, Просмотр, Центры сертификации, Импорт, файл `traefik/cert/rootCA.pem`, галка доверия для сайтов.

Проверка, что имя и сертификат сходятся. Ответ `{"status":"ok"}`:

```bash
curl --cacert traefik/cert/rootCA.pem https://api.marshall.io/health
```

`--cacert` нужен curl в любом случае, даже после установки CA в систему: так команда не зависит от хранилища ОС. Браузер после установки CA открывает `https://buyer.marshall.io` без предупреждения.

## Лид

Ключ байера лежит на странице Leads в его кабинете. Хост API совпадает с апексом кабинета: ключ Marshall уходит на `api.marshall.io`, ключ Chase на `api.chase.io`.

Сертификат для curl берётся из репозитория, флаг `--cacert`:

```bash
curl -X POST https://api.marshall.io/v1/lead \
  --cacert traefik/cert/rootCA.pem \
  -H 'Authorization: Bearer КЛЮЧ_ИЗ_КАБИНЕТА' \
  -H 'Content-Type: application/json' \
  -d '{"phone":"+4915112345678","email":"ada@example.com","first_name":"Ada","last_name":"Lovelace","country_code":"DE","ip":"192.0.2.10","aff_sub":"demo"}'
```

Ответ `{"uuid":"..."}` значит, что API принял лид. В таблице кабинета он появляется после обработки, кнопка Refresh на странице Leads. Все поля в теле обязательны. Без почты, имени или фамилии API всё равно вернёт uuid, а в кабинет лид не попадёт.

## Доступ

Пароль кабинета у всех один: `rider-local`.

| Кто | Почта | Куда входить |
| --- | --- | --- |
| Владелец | `owner@rider.io` | `https://id.rider.io`, кабинет `https://owner.rider.io` |
| Байер Chase | `buyer@chase.io` | `https://id.chase.io`, кабинет `https://buyer.chase.io` |
| Партнёр Chase | `partner@chase.io` | `https://id.chase.io`, кабинет `https://seller.chase.io` |
| Админ Chase | `admin@chase.io` | `https://id.chase.io`, кабинет `https://admin.chase.io` |
| Байер Marshall | `buyer@marshall.io` | `https://id.marshall.io`, кабинет `https://buyer.marshall.io` |
| Партнёр Marshall | `partner@marshall.io` | `https://id.marshall.io`, кабинет `https://seller.marshall.io` |
| Админ Marshall | `admin@marshall.io` | `https://id.marshall.io`, кабинет `https://admin.marshall.io` |

## Деплой на стенд

Код в `/opt/rider` на `2.29.205.3`. После `git push` в `main`:

```bash
cd /path/to/rider
./scripts/deploy-stage.sh
```

Скрипт по умолчанию делает `git fetch` на стенде. Если deploy key ещё не добавлен в GitHub, он сам переключится на `rsync`.

### Deploy key (один раз)

На стенде: `/root/.ssh/github_fiveminutes`, `origin` через хост `github.com-fiveminutes`. Добавь **read-only** deploy key в репозиторий:

```bash
export GITHUB_TOKEN='classic PAT с scope repo'
./scripts/register-github-deploy-key.sh
```

Либо вручную: [Deploy keys](https://github.com/gfsx0259/fiveminutes/settings/keys) — публичный ключ:

```bash
ssh root@2.29.205.3 'cat /root/.ssh/github_fiveminutes.pub'
```

Проверка:

```bash
ssh root@2.29.205.3 'cd /opt/rider && git fetch origin && git log -1 --oneline origin/main'
```

### Принудительный rsync

```bash
RIDER_DEPLOY_RSYNC=1 ./scripts/deploy-stage.sh
```

Серты Traefik (mkcert) — в `meta/cert` рядом с репо; скрипт докинет их в `traefik/cert/`, если на стенде нет wildcard-файлов.
