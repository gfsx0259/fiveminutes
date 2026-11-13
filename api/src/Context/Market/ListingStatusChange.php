<?php

declare(strict_types=1);

namespace App\Context\Market;

use DateTimeInterface;

final class ListingStatusChange
{
    public function __construct(
        private ListingStatus $status,
        private DateTimeInterface $at,
    ) {}

    public function getStatus(): ListingStatus
    {
        return $this->status;
    }

    public function getAt(): DateTimeInterface
    {
        return $this->at;
    }
}
