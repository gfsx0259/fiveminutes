<?php

declare(strict_types=1);

namespace App\Context\Market;

use App\Value\Money;
use InvalidArgumentException;

final readonly class Markup
{
    public function __construct(private int $percentOnTop = 10)
    {
        if ($percentOnTop < 0) {
            throw new InvalidArgumentException('Percent must be zero or positive');
        }
    }

    public function partnerPays(Money $buyerPrice): Money
    {
        return $buyerPrice->plusPercent($this->percentOnTop);
    }
}
