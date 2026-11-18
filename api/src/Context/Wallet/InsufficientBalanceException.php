<?php

declare(strict_types=1);

namespace App\Context\Wallet;

use DomainException;

final class InsufficientBalanceException extends DomainException
{
}