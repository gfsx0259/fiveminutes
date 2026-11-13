<?php

declare(strict_types=1);

namespace App\Value;

use InvalidArgumentException;

final readonly class Money
{
    public function __construct(private int $cents)
    {
        if ($this->cents <= 0) {
            throw new InvalidArgumentException('Price must be positive');
        }
    }

    public function cents(): int
    {
        return $this->cents;
    }

    public function isBelow(self $other): bool
    {
        return $this->cents < $other->cents;
    }

    public function plusPercent(int $percent): self
    {
        if ($percent < 0) {
            throw new InvalidArgumentException('Percent must be zero or positive');
        }

        return new self(intdiv($this->cents * (100 + $percent) + 50, 100));
    }
}
