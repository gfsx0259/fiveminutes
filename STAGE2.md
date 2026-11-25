# Стенд с чистой машины

Стенд уже запущен на `2.29.205.3`. Доступы в [ACCESS.md](ACCESS.md) и в таблице внизу.

Сертификат стенда выписан mkcert и в публичный список доверенных не входит. Корневой CA лежит в репозитории: `traefik/cert/rootCA.pem`. Это дев-сертификат, им подписаны `*.rider.io`, `*.chase.io` и `*.marshall.io`. Новый mkcert на своей машине выпускать не надо: у него будет другой корень, и браузер стенду не поверит.

## Имена

В `/etc/hosts` нет масок, поэтому каждое имя пишется отдельно. Строка одна:

```text
2.29.205.3 id.rider.io id.chase.io id.marshall.io owner.rider.io api.rider.io api.chase.io api.marshall.io buyer.chase.io partner.chase.io admin.chase.io buyer.marshall.io partner.marshall.io admin.marshall.io kafka.rider.io
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

## Доступ

Пароль кабинета у всех один: `rider-local`.

| Кто | Почта | Куда входить |
| --- | --- | --- |
| Владелец | `owner@rider.io` | `https://id.rider.io`, кабинет `https://owner.rider.io` |
| Байер Chase | `buyer@chase.io` | `https://id.chase.io`, кабинет `https://buyer.chase.io` |
| Партнёр Chase | `partner@chase.io` | `https://id.chase.io`, кабинет `https://partner.chase.io` |
| Админ Chase | `admin@chase.io` | `https://id.chase.io`, кабинет `https://admin.chase.io` |
| Байер Marshall | `buyer@marshall.io` | `https://id.marshall.io`, кабинет `https://buyer.marshall.io` |
| Партнёр Marshall | `partner@marshall.io` | `https://id.marshall.io`, кабинет `https://partner.marshall.io` |
| Админ Marshall | `admin@marshall.io` | `https://id.marshall.io`, кабинет `https://admin.marshall.io` |
