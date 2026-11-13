<?php

declare(strict_types=1);

namespace App\Context\Organization;

use App\Clock;
use Ramsey\Uuid\UuidFactoryInterface;

final readonly class OrganizationFactory
{
    public function __construct(
        private UuidFactoryInterface $uuids,
        private Clock $clock,
    ) {}

    public function create(string $name, string $apex): Organization
    {
        $now = $this->clock->now();

        return new Organization($this->uuids->uuid4()->toString(), $name, $apex, $now, $now);
    }
}
