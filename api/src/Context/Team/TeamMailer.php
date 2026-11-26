<?php

declare(strict_types=1);

namespace App\Context\Team;

use DateTimeInterface;
use Psr\Log\LoggerInterface;
use Symfony\Component\Mailer\Exception\TransportExceptionInterface;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Mime\Email;

final readonly class TeamMailer
{
    public function __construct(
        private MailerInterface $mailer,
        private LoggerInterface $logger,
        private string $from,
    ) {}

    public function invite(Member $member, string $inviter, string $organization, string $team, string $signUpUrl): void
    {
        $expires = $member->getExpiresAt()?->format('j M Y') ?? '';
        $this->send(
            [$member->getEmail()],
            sprintf('%s invited you to %s on Rider', $inviter, $organization),
            implode("\n\n", [
                sprintf('%s invited you to the %s team of %s on Rider.', $inviter, $team, $organization),
                sprintf('Sign up with this email address to join: %s', $signUpUrl),
                $expires === '' ? '' : sprintf('The invite is valid until %s.', $expires),
            ]),
        );
    }

    /**
     * @param list<string> $recipients
     */
    public function budgetAlert(array $recipients, TeamUnit $unit, int $threshold, int $usedCents, DateTimeInterface $at): void
    {
        $budget = (int) $unit->getBudgetCents();
        $this->send(
            $recipients,
            sprintf('%s has used %d%% of its budget', $unit->getName(), $threshold),
            implode("\n\n", [
                sprintf(
                    'The %s team has used %s of its %s %s budget, %d%%.',
                    $unit->getName(),
                    $this->money($usedCents),
                    strtolower($unit->getBudgetPeriod()->label()),
                    $this->money($budget),
                    $budget > 0 ? (int) floor($usedCents * 100 / $budget) : 100,
                ),
                $threshold >= 100
                    ? 'New orders for this team are blocked until the budget is raised or the period resets.'
                    : 'New orders are still allowed until the budget is used up.',
                sprintf('Checked at %s UTC.', $at->format('j M Y H:i')),
            ]),
        );
    }

    /**
     * @param list<string> $recipients
     */
    private function send(array $recipients, string $subject, string $text): void
    {
        $recipients = array_values(array_unique(array_filter($recipients)));
        if ($recipients === []) {
            return;
        }

        try {
            $this->mailer->send((new Email())->from($this->from)->to(...$recipients)->subject($subject)->text(trim($text)));
        } catch (TransportExceptionInterface $exception) {
            $this->logger->warning('Team mail was not sent', ['subject' => $subject, 'error' => $exception->getMessage()]);
        }
    }

    private function money(int $cents): string
    {
        return '$' . number_format($cents / 100, 2);
    }
}
