const http = require('http')

const KRATOS = process.env.KRATOS_PUBLIC_URL || 'http://kratos:4433'
const PORT = Number(process.env.PORT || 3000)
const kratosUrl = new URL(KRATOS)

const page = (title, body) => `<!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>${title}</title>
  <style>
    :root { color-scheme: light; }
    body { margin: 0; min-height: 100vh; display: grid; place-items: center; background: #f4f1ea; color: #1c1915; font: 16px/1.45 ui-sans-serif, system-ui, sans-serif; }
    main { width: min(440px, calc(100% - 32px)); background: #fff; border: 1px solid #e4ddd2; border-radius: 16px; padding: 28px; box-shadow: 0 12px 40px rgba(40, 30, 10, 0.06); }
    h1 { font-size: 22px; margin: 0 0 4px; }
    .host { display: inline-block; margin: 8px 0 18px; padding: 4px 8px; border-radius: 999px; background: #efe7da; font: 13px/1 ui-monospace, monospace; }
    label { display: grid; gap: 6px; margin: 0 0 12px; font-size: 14px; }
    input { font: inherit; padding: 10px 12px; border: 1px solid #d9d0c4; border-radius: 10px; }
    button, .link { font: inherit; }
    button { width: 100%; margin-top: 6px; padding: 10px 12px; border: 0; border-radius: 10px; background: #1c1915; color: #fff; cursor: pointer; }
    .row { display: flex; justify-content: space-between; gap: 12px; margin-top: 16px; font-size: 14px; }
    a { color: #6b4e16; }
    .error { color: #9b2c2c; font-size: 14px; margin: 0 0 12px; }
    dl { display: grid; grid-template-columns: 110px 1fr; gap: 8px 12px; margin: 0; }
    dt { color: #6f675e; }
    dd { margin: 0; overflow-wrap: anywhere; }
    pre { margin: 16px 0 0; padding: 12px; border-radius: 10px; background: #f7f4ee; overflow: auto; font-size: 12px; }
    .hint { color: #6f675e; font-size: 13px; margin: 0 0 16px; }
  </style>
</head>
<body>
  <main>
    <h1>${title}</h1>
    <div class="host" id="host"></div>
    ${body}
  </main>
  <script>document.getElementById('host').textContent = location.host</script>
</body>
</html>`

const loginScript = `
<script>
const flowId = new URLSearchParams(location.search).get('flow')
if (!flowId) {
  location.replace('/self-service/login/browser?return_to=' + encodeURIComponent(location.origin + '/session'))
} else {
  loadFlow('/self-service/login/flows?id=' + encodeURIComponent(flowId))
}
async function loadFlow(url) {
  const response = await fetch(url, { credentials: 'include', headers: { accept: 'application/json' } })
  const flow = await response.json()
  const form = document.getElementById('flow')
  form.action = sameHost(flow.ui.action)
  form.method = (flow.ui.method || 'POST').toUpperCase()
  for (const message of flow.ui.messages || []) addMessage(form, message.text)
  for (const node of flow.ui.nodes || []) form.append(field(node))
}
function sameHost(action) {
  const url = new URL(action, location.origin)
  url.protocol = location.protocol
  url.host = location.host
  return url.toString()
}
function addMessage(form, text) {
  const p = document.createElement('p')
  p.className = 'error'
  p.textContent = text
  form.append(p)
}
function field(node) {
  const attributes = node.attributes || {}
  for (const message of node.messages || []) addMessage(document.getElementById('flow'), message.text)
  if (attributes.type === 'submit') {
    const button = document.createElement('button')
    button.type = 'submit'
    button.name = attributes.name || 'method'
    button.value = attributes.value || 'password'
    button.textContent = node.meta && node.meta.label ? node.meta.label.text : 'Sign in'
    return button
  }
  if (!attributes.name) return document.createElement('span')
  const input = document.createElement('input')
  input.name = attributes.name
  input.type = attributes.type || 'text'
  if (attributes.value != null) input.value = attributes.value
  if (attributes.required) input.required = true
  input.autocomplete = attributes.autocomplete || (attributes.name === 'password' ? 'current-password' : 'username')
  if (attributes.type === 'hidden') return input
  const label = document.createElement('label')
  const caption = document.createElement('span')
  caption.textContent = node.meta && node.meta.label ? node.meta.label.text : attributes.name
  label.append(caption, input)
  return label
}
</script>`

const registrationScript = loginScript
  .replace('/self-service/login/browser?', '/self-service/registration/browser?')
  .replace('/self-service/login/flows?', '/self-service/registration/flows?')
  .replace("'Sign in'", "'Create account'")

const sessionScript = `
<script>
const others = ['id.rider.io', 'id.chase.io', 'id.marshall.io'].filter((host) => host !== location.host)
const links = document.getElementById('others')
for (const host of others) {
  const a = document.createElement('a')
  a.href = 'https://' + host + '/session'
  a.textContent = host
  links.append(a)
}
document.getElementById('logout').addEventListener('click', async () => {
  const response = await fetch('/self-service/logout/browser', { credentials: 'include', headers: { accept: 'application/json' } })
  const body = await response.json()
  if (body.logout_url) {
    const url = new URL(body.logout_url, location.origin)
    url.protocol = location.protocol
    url.host = location.host
    location.href = url.toString()
  }
})
fetch('/sessions/whoami', { credentials: 'include', headers: { accept: 'application/json' } })
  .then(async (response) => {
    if (response.status === 401) {
      location.replace('/login')
      return
    }
    const session = await response.json()
    document.getElementById('email').textContent = session.identity && session.identity.traits ? session.identity.traits.email : ''
    document.getElementById('sid').textContent = session.id || ''
    document.getElementById('at').textContent = session.authenticated_at || ''
    document.getElementById('raw').textContent = JSON.stringify(session, null, 2)
  })
</script>`

const routes = {
  '/': (res) => redirect(res, '/session'),
  '/login': (res) => html(res, page('Sign in', `
    <p class="hint">Local account: owner@rider.io / rider-local</p>
    <form id="flow"></form>
    <div class="row"><a href="/registration">Create an account</a></div>
    ${loginScript}`)),
  '/registration': (res) => html(res, page('Create account', `
    <form id="flow"></form>
    <div class="row"><a href="/login">Back to sign in</a></div>
    ${registrationScript}`)),
  '/session': (res) => html(res, page('Session', `
    <dl>
      <dt>Email</dt><dd id="email">…</dd>
      <dt>Session</dt><dd id="sid">…</dd>
      <dt>Signed in</dt><dd id="at">…</dd>
    </dl>
    <pre id="raw"></pre>
    <div class="row" id="others"></div>
    <button id="logout" type="button">Sign out</button>
    ${sessionScript}`)),
  '/error': (res) => html(res, page('Sign-in error', `<p class="error">Kratos could not continue this flow.</p><div class="row"><a href="/login">Try again</a></div>`)),
}

function html(res, body) {
  res.writeHead(200, { 'content-type': 'text/html; charset=utf-8', 'cache-control': 'no-store' })
  res.end(body)
}

function redirect(res, location) {
  res.writeHead(302, { location })
  res.end()
}

function publicRequest(req) {
  const host = String(req.headers['x-forwarded-host'] || req.headers.host || '').split(',')[0].trim()
  const proto = String(req.headers['x-forwarded-proto'] || 'https').split(',')[0].trim()
  return { host, proto, origin: `${proto}://${host}` }
}

function isInternalHost(hostname) {
  return hostname === kratosUrl.hostname || !hostname.includes('.')
}

function rewriteLocation(value, pub) {
  if (!value) return value
  if (value.startsWith('/')) return pub.origin + value
  try {
    const url = new URL(value)
    if (url.host === pub.host || !isInternalHost(url.hostname)) return value
    url.protocol = pub.proto + ':'
    url.host = pub.host
    return url.toString()
  } catch {
    return value
  }
}

function rewriteJson(text, pub) {
  return text.replace(/https?:\/\/[a-zA-Z0-9.-]+(?::\d+)?/g, (origin) => {
    try {
      const url = new URL(origin)
      if (!isInternalHost(url.hostname)) return origin
      return pub.origin
    } catch {
      return origin
    }
  })
}

function proxy(req, res) {
  const pub = publicRequest(req)
  const headers = {}
  for (const [key, value] of Object.entries(req.headers)) {
    if (['connection', 'keep-alive', 'proxy-connection', 'transfer-encoding', 'upgrade', 'host'].includes(key)) continue
    headers[key] = value
  }
  headers.host = pub.host
  headers['x-forwarded-proto'] = pub.proto
  headers['x-forwarded-host'] = pub.host

  const upstream = http.request({
    hostname: kratosUrl.hostname,
    port: kratosUrl.port || 80,
    path: req.url,
    method: req.method,
    headers,
  }, (incoming) => {
    const responseHeaders = { ...incoming.headers }
    if (responseHeaders.location) {
      const locations = Array.isArray(responseHeaders.location) ? responseHeaders.location : [responseHeaders.location]
      const rewritten = locations.map((location) => rewriteLocation(location, pub))
      responseHeaders.location = Array.isArray(responseHeaders.location) ? rewritten : rewritten[0]
    }
    const chunks = []
    incoming.on('data', (chunk) => chunks.push(chunk))
    incoming.on('end', () => {
      let body = Buffer.concat(chunks)
      const type = String(responseHeaders['content-type'] || '')
      if (type.includes('json')) body = Buffer.from(rewriteJson(body.toString('utf8'), pub))
      delete responseHeaders['transfer-encoding']
      responseHeaders['content-length'] = String(body.length)
      res.writeHead(incoming.statusCode || 502, responseHeaders)
      res.end(body)
    })
  })
  upstream.on('error', () => {
    if (!res.headersSent) res.writeHead(502, { 'content-type': 'text/plain; charset=utf-8' })
    res.end('Kratos is not reachable')
  })
  req.pipe(upstream)
}

const server = http.createServer((req, res) => {
  const path = (req.url || '/').split('?')[0]
  if (path.startsWith('/self-service') || path.startsWith('/sessions')) {
    proxy(req, res)
    return
  }
  const handler = routes[path]
  if (!handler) {
    res.writeHead(404, { 'content-type': 'text/plain; charset=utf-8' })
    res.end('Not found')
    return
  }
  if (req.method !== 'GET' && req.method !== 'HEAD') {
    res.writeHead(405, { allow: 'GET' })
    res.end()
    return
  }
  handler(res)
})

server.listen(PORT, '0.0.0.0')
