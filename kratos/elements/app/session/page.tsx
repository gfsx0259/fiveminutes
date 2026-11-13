"use client"

import { useEffect, useState } from "react"

export default function SessionPage() {
  const [body, setBody] = useState("Loading session…")

  useEffect(() => {
    const returnTo = encodeURIComponent(`${window.location.origin}/session`)
    fetch("/sessions/whoami", { credentials: "include", headers: { accept: "application/json" } })
      .then(async (response) => {
        if (response.status === 401) {
          window.location.replace(`/auth/login?return_to=${returnTo}`)
          return
        }
        const session = await response.json()
        const email = session.identity?.traits?.email ?? ""
        setBody(`${window.location.host}\n${email}\n${session.id}\n\n${JSON.stringify(session, null, 2)}`)
      })
      .catch(() => {
        window.location.replace(`/auth/login?return_to=${returnTo}`)
      })
  }, [])

  return (
    <main style={{ fontFamily: "ui-sans-serif, system-ui, sans-serif", padding: 32 }}>
      <h1>Session</h1>
      <pre style={{ whiteSpace: "pre-wrap" }}>{body}</pre>
      <p><a href="/settings">Settings</a></p>
    </main>
  )
}
