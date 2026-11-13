import { headers } from "next/headers"
import { redirect } from "next/navigation"

type SearchParams = Record<string, string | string[] | undefined>

export async function ensureReturnTo(path: string, searchParams: Promise<SearchParams>) {
  const params = await searchParams
  if (params.flow || params.return_to) {
    return params
  }

  const requestHeaders = await headers()
  const host = requestHeaders.get("x-forwarded-host") || requestHeaders.get("host")
  const proto = requestHeaders.get("x-forwarded-proto") || "https"
  redirect(`${path}?return_to=${encodeURIComponent(`${proto}://${host}/session`)}`)
}
