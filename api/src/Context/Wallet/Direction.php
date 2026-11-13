<?php

declare(strict_types=1);

namespace App\Context\Wallet;

enum Direction: string
{
    case Credit = 'credit';
    case Debit = 'debit';
}
