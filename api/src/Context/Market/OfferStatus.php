<?php

declare(strict_types=1);

namespace App\Context\Market;

enum OfferStatus: string
{
    case Pending = 'pending';
    case Approved = 'approved';
    case Rejected = 'rejected';
}
