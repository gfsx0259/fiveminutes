<?php

declare(strict_types=1);

namespace App\Context\Identity;

use RuntimeException;

final class NoAccountException extends RuntimeException
{
    public function __construct()
    {
        parent::__construct('No account');
    }
}
