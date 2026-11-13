"use client"

import { useEffect } from "react"

export default function RedirectPage() {
  useEffect(() => {
    window.location.replace("/session")
  }, [])

  return <div>Redirecting…</div>
}
