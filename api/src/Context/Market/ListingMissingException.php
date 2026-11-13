<?php

declare(strict_types=1);

namespace App\Context\Market;

use RuntimeException;

final class ListingMissingException extends RuntimeException
{
    public function __construct()
    {
        parent::__construct('Listing was not found');
    }
}
