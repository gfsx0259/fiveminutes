<?php

declare(strict_types=1);

namespace App\Context\Market;

use App\Context\Organization\OrganizationRepository;

final readonly class OrganizationMarkup
{
    private const int DEFAULT_PERCENT = 10;

    public function __construct(private OrganizationRepository $organizations) {}

    public function forOrganization(string $organizationId): Markup
    {
        $organization = $this->organizations->findById($organizationId);

        return new Markup($organization?->getMarkupPercent() ?? self::DEFAULT_PERCENT);
    }
}