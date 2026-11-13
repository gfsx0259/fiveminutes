<?php

declare(strict_types=1);

namespace App\Context\User;

use RuntimeException;

final class NotBuyerException extends RuntimeException
{
    public function __construct()
    {
        parent::__construct('Only a buyer can submit leads');
    }
}
