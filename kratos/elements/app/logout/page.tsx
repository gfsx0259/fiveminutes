"use client"

import { useEffect } from "react"

export default function LogoutPage() {
  useEffect(() => {
    const requested = new URLSearchParams(window.location.search).get("return_to")
    const apex = window.location.hostname.split(".").slice(-2).join(".")
    let returnTo = `${window.location.origin}/auth/login`
    if (requested) {
      try {
        const url = new URL(requested)
        const requestedApex = url.hostname.split(".").slice(-2).join(".")
        if (url.protocol === "https:" && requestedApex === apex) {
          returnTo = url.toString()
        }
      } catch {
        returnTo = `${window.location.origin}/auth/login`
      }
    }

    fetch(`/self-service/logout/browser?return_to=${encodeURIComponent(returnTo)}`, {
      credentials: "include",
      headers: { accept: "application/json" },
    })
      .then(async (response) => {
        if (!response.ok) {
          window.location.replace(returnTo)
          return
        }
        const body = await response.json() as { logout_url?: string }
        if (!body.logout_url) {
          window.location.replace(returnTo)
          return
        }
        const url = new URL(body.logout_url, window.location.origin)
        url.protocol = window.location.protocol
        url.host = window.location.host
        window.location.replace(url.toString())
      })
      .catch(() => {
        window.location.replace(returnTo)
      })
  }, [])

  return (
    <main style={{ fontFamily: "ui-sans-serif, system-ui, sans-serif", padding: 32 }}>
      Signing out…
    </main>
  )
}
