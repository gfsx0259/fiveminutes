// Copyright © 2024 Ory Corp
// SPDX-License-Identifier: Apache-2.0

import { Registration } from "@ory/elements-react/theme"
import { getRegistrationFlow, OryPageParams } from "@ory/nextjs/app"
import { UiNode, UiNodeInputAttributes } from "@ory/client-fetch";
import config from "@/ory.config"
import CustomCard from "@/components/custom-card";
import { ensureReturnTo } from "@/lib/return-to";

export default async function RegistrationPage(props: OryPageParams) {
    const params = await ensureReturnTo("/auth/registration", props.searchParams)
    const flow = await getRegistrationFlow(
        config,
        Promise.resolve(params)
    )

    if (!flow) {
      return null
    }

    let email: string | undefined

    if (flow.request_url) {
        try {
            email = new URL(flow.request_url).searchParams.get('traits.email') || undefined
        } catch (e) {
            console.info('Failed to parse request_uri:', e);
        }
    }

    console.info("Prefilled email from request_uri:", email);

    flow.ui.nodes = flow.ui.nodes.map((node: UiNode) => {
        if (
            (node.attributes as UiNodeInputAttributes).name === 'traits.email' &&
            email
        ) {

            console.log("node", node);

            return {
                ...node,
                attributes: {
                    ...node.attributes,
                    value: email,
                    disabled: true,
                },
            }
        }
        return node
    });

    return (
    <Registration
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
