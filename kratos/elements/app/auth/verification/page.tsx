// Copyright © 2024 Ory Corp
// SPDX-License-Identifier: Apache-2.0

import CustomCardHeader from "@/components/custom-card-header"
import { Verification } from "@ory/elements-react/theme"
import { getVerificationFlow, OryPageParams } from "@ory/nextjs/app"

import CustomCard from "@/components/custom-card";
import { browserConfig } from "@/lib/browser-config";

export default async function VerificationPage(props: OryPageParams) {
  const config = await browserConfig()
  const flow = await getVerificationFlow(config, props.searchParams)

  if (!flow) {
    return null
  }

  return (
    <Verification
      flow={flow}
      config={config}
      components={{
        Card: {
          Header: CustomCardHeader,
          Root: CustomCard,
        },
      }}
    />
  )
}
