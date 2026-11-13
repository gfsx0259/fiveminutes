import { headers } from "next/headers"
import type { OryClientConfiguration } from "@ory/elements-react"

import config from "@/ory.config"

export async function browserConfig(): Promise<OryClientConfiguration> {
  const requestHeaders = await headers()
  const host = requestHeaders.get("x-forwarded-host") || requestHeaders.get("host")
  const proto = requestHeaders.get("x-forwarded-proto") || "https"
  if (!host) {
    throw new Error("The login page has no host header.")
  }

  return {
    ...config,
    sdk: {
      ...config.sdk,
      url: `${proto}://${host}`,
    },
  }
}
