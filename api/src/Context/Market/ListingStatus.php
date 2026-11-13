<?php

declare(strict_types=1);

namespace App\Context\Market;

enum ListingStatus: string
{
    case Open = 'open';
    case Taken = 'taken';
    case Closed = 'closed';
}
