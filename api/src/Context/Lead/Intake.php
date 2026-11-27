<?php

declare(strict_types=1);

namespace App\Context\Lead;

use App\Context\User\NotBuyerException;
use App\Context\User\User;
use App\Context\User\UserRepository;
use App\Context\User\UserRole;

final readonly class Intake
{
    public function __construct(
        private UserRepository $users,
        private LeadFactory $leads,
        private LeadQueue $queue,
    ) {}

    /**
     * @param array<mixed, mixed> $body
     */
    public function accept(string $apiKey, array $body): Lead
    {
        return $this->receive($this->buyer($apiKey), $body);
    }

    /**
     * One buyer check for the whole batch; a bad row does not stop the others.
     *
     * @param list<array<mixed, mixed>> $rows
     * @return array{accepted: int, rejected: list<array{line: int, errors: array<string, string>}>}
     */
    public function acceptMany(string $apiKey, array $rows): array
    {
        $buyer = $this->buyer($apiKey);
        $accepted = 0;
        $rejected = [];
        foreach ($rows as $index => $row) {
            try {
                $this->receive($buyer, $row);
                ++$accepted;
            } catch (LeadRejectedException $exception) {
                // Line 1 is the CSV header, so the first lead sits on line 2.
                $rejected[] = ['line' => $index + 2, 'errors' => $exception->errors()];
            }
        }

        return ['accepted' => $accepted, 'rejected' => $rejected];
    }

    private function buyer(string $apiKey): User
    {
        $buyer = $this->users->findByApiKey($apiKey);
        if ($buyer === null) {
            throw new UnknownApiKeyException();
        }
        if ($buyer->getRole() !== UserRole::Buyer) {
            throw new NotBuyerException();
        }

        return $buyer;
    }

    /**
     * @param array<mixed, mixed> $body
     */
    private function receive(User $buyer, array $body): Lead
    {
        $lead = $this->leads->receive($buyer, new LeadSubmission($body));
        $this->queue->publish($lead);

        return $lead;
    }
}
