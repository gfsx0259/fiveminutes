// Copyright © 2024 Ory Corp
// SPDX-License-Identifier: Apache-2.0

import { Login } from "@ory/elements-react/theme"
import { getLoginFlow, OryPageParams } from "@ory/nextjs/app"

import CustomCard from "@/components/custom-card";
import { browserConfig } from "@/lib/browser-config";
import { ensureReturnTo } from "@/lib/return-to";

export default async function LoginPage(props: OryPageParams) {
  const config = await browserConfig()
  const params = await ensureReturnTo("/auth/login", props.searchParams)
  const flow = await getLoginFlow(config, Promise.resolve(params))

  if (!flow) {
    return null
  }

  return (
    <Login
      flow={flow}
      config={config}
      components={{
        Card: {
            Root: CustomCard,
        },
      }}
    />
  )
}
