<?php

declare(strict_types=1);

namespace App\Context\Lead;

use App\Context\Market\Permit;
use App\Context\User\NotBuyerException;
use App\Context\User\UserRepository;
use App\Context\User\UserRole;

final readonly class Intake
{
    public function __construct(
        private UserRepository $users,
        private LeadFactory $leads,
        private LeadQueue $queue,
        private Permit $permits,
    ) {}

    /**
     * @param array<mixed, mixed> $body
     */
    public function accept(string $apiKey, array $body): Lead
    {
        $buyer = $this->users->findByApiKey($apiKey);
        if ($buyer === null) {
            throw new UnknownApiKeyException();
        }
        if ($buyer->getRole() !== UserRole::Buyer) {
            throw new NotBuyerException();
        }

        $lead = $this->leads->receive($buyer, new LeadSubmission($body));
        $this->permits->assertRecognized($lead);
        $this->queue->publish($lead);

        return $lead;
    }
}
