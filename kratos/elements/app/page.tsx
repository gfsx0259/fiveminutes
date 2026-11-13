import { headers } from "next/headers"
import { redirect } from "next/navigation"
import { getServerSession } from "@ory/nextjs/app"

export default async function HomePage() {
  const session = await getServerSession().catch(() => null)
  if (session) {
    redirect("/session")
  }

  const requestHeaders = await headers()
  const host = requestHeaders.get("x-forwarded-host") || requestHeaders.get("host")
  const proto = requestHeaders.get("x-forwarded-proto") || "https"
  redirect(`/auth/login?return_to=${encodeURIComponent(`${proto}://${host}/session`)}`)
}
