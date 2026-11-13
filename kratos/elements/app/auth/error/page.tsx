import Link from "next/link"
import CustomCard from "@/components/custom-card"
import { authPrimaryButtonClassName } from "@/components/sign-out-button"

export default function AuthErrorPage() {
  return (
    <CustomCard>
      <div className="flex flex-col gap-8">
        <header className="flex flex-col gap-2">
          <h2 className="text-lg leading-normal font-semibold text-interface-foreground-default-primary">
            Something went wrong
          </h2>
          <p className="leading-normal text-interface-foreground-default-secondary">
            The sign-in session could not be completed. Please try again.
          </p>
        </header>
        <Link href="/auth/login" className={authPrimaryButtonClassName}>
          Sign in
        </Link>
      </div>
    </CustomCard>
  )
}
