import { getServerSession } from "@ory/nextjs/app"
import Link from "next/link"
import CustomCard from "@/components/custom-card"
import SignOutButton, {
  authPrimaryButtonClassName,
} from "@/components/sign-out-button"

export default async function NoAccessPage() {
  const session = await getServerSession().catch(() => null)
  const traits = session?.identity?.traits as
    | { email?: string; username?: string }
    | undefined
  const account = traits?.email ?? traits?.username

  return (
    <CustomCard>
      <div className="flex flex-col gap-8">
        <header className="flex flex-col gap-2">
          <h2 className="text-lg leading-normal font-semibold text-interface-foreground-default-primary">
            No dashboard yet
          </h2>
          <p className="leading-normal text-interface-foreground-default-secondary">
            {account ? (
              <>
                Signed in as{" "}
                <span className="font-medium text-interface-foreground-default-primary">
                  {account}
                </span>
                .{" "}
              </>
            ) : null}
            This account is not assigned to a dashboard. Ask an administrator to
            grant you access.
          </p>
        </header>
        {session ? (
          <SignOutButton />
        ) : (
          <Link href="/auth/login" className={authPrimaryButtonClassName}>
            Sign in
          </Link>
        )}
      </div>
    </CustomCard>
  )
}
