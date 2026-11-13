<?php

declare(strict_types=1);

namespace App\Value;

final class Money
{
    private int $cents;

    public function __construct(int $cents)
    {
        if ($cents <= 0) {
            throw new \InvalidArgumentException('Price must be positive');
        }

        $this->cents = $cents;
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
            throw new \InvalidArgumentException('Percent must be zero or positive');
        }

        return new self(intdiv($this->cents * (100 + $percent) + 50, 100));
    }
}
