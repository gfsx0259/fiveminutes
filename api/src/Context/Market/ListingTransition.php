<?php

declare(strict_types=1);

namespace App\Context\Market;

use App\Clock;
use DomainException;

final readonly class ListingTransition
{
    public function __construct(private Clock $clock) {}

    public function change(ListingStatus $current, ListingStatus $next): ListingStatusChange
    {
        if ($current !== ListingStatus::Open) {
            throw new DomainException('Listing is not open');
        }

        return new ListingStatusChange($next, $this->clock->now());
    }
}
