import type { OryClientConfiguration } from "@ory/elements-react"

const config: OryClientConfiguration = {
  sdk: {
    // Empty base makes the browser call /self-service on the host it opened.
    url: "",
  },
  project: {
    default_redirect_url: "/session",
    error_ui_url: "/auth/error",
    name: "Rider",
    registration_enabled: true,
    verification_enabled: false,
    recovery_enabled: false,
    registration_ui_url: "/auth/registration",
    verification_ui_url: "/auth/verification",
    recovery_ui_url: "/auth/recovery",
    login_ui_url: "/auth/login",
    settings_ui_url: "/settings",
    default_locale: "en",
    enabled_locales: ["en", "ru"],
    locale_behavior: "force_default",
    translations: [],
  },
}

export default config
