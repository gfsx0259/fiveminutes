"use client"

import { useState } from "react"

export const authPrimaryButtonClassName =
  "relative flex justify-center gap-3 overflow-hidden rounded-buttons p-4 leading-none font-medium ring-1 ring-inset transition-colors duration-100 ease-linear bg-button-primary-background-default text-button-primary-foreground-default ring-button-primary-border-default hover:bg-button-primary-background-hover hover:text-button-primary-foreground-hover hover:ring-button-primary-border-hover disabled:cursor-wait"

export default function SignOutButton({
  className = authPrimaryButtonClassName,
}: {
  className?: string
}) {
  const [pending, setPending] = useState(false)

  const onClick = async () => {
    if (pending) {
      return
    }

    setPending(true)

    try {
      const returnTo = `${window.location.origin}/auth/login`
      const res = await fetch(
        `/self-service/logout/browser?return_to=${encodeURIComponent(returnTo)}`,
        { credentials: "include" },
      )

      if (!res.ok) {
        window.location.href = "/auth/login"
        return
      }

      const body = (await res.json()) as { logout_url?: string }
      window.location.href = body.logout_url ?? "/auth/login"
    } catch {
      window.location.href = "/auth/login"
    }
  }

  return (
    <button
      type="button"
      className={className}
      onClick={onClick}
      disabled={pending}
    >
      Sign out
    </button>
  )
}
