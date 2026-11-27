<?php

declare(strict_types=1);

namespace App\Context\Lead;

use App\Clock;
use App\Context\User\User;
use Ramsey\Uuid\UuidFactoryInterface;

final readonly class LeadFactory
{
    public function __construct(
        private UuidFactoryInterface $uuids,
        private Clock $clock,
    ) {}

    public function receive(User $buyer, LeadSubmission $submission): Lead
    {
        $now = $this->clock->now();
        $uuid = $this->uuids->uuid4()->toString();

        return (new Lead(
            $uuid,
            $buyer->getOrganizationId(),
            $buyer->getId(),
            null,
            null,
            $submission->email(),
            $submission->phone(),
            $submission->firstName(),
            $submission->lastName(),
            $submission->country(),
            $submission->trafficSource(),
            $submission->age(),
            $submission->gender(),
            $submission->ip(),
            $submission->vertical(),
            $uuid,
            LeadStatus::New,
            0,
            $now,
            $now,
        ))->withMeta($submission->meta())->withTest($submission->isTest());
    }
}
