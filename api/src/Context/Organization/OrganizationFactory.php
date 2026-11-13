<?php

declare(strict_types=1);

namespace App\Context\Organization;

use App\Clock;
use App\UuidGenerator;

final class OrganizationFactory
{
    public function __construct(
        private UuidGenerator $uuids,
        private Clock $clock,
    ) {}

    public function create(string $name, string $apex): Organization
    {
        $now = $this->clock->now();

        return new Organization($this->uuids->v4(), $name, $apex, $now, $now);
    }
}
