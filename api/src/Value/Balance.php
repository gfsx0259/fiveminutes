<?php

declare(strict_types=1);

namespace App\Value;

use DomainException;
use InvalidArgumentException;

final readonly class Balance
{
    public function __construct(private int $cents)
    {
        if ($this->cents < 0) {
            throw new InvalidArgumentException('Balance cannot be negative');
        }
    }

    public function cents(): int
    {
        return $this->cents;
    }

    public function credit(Money $amount): self
    {
        return new self($this->cents + $amount->cents());
    }

    public function debit(Money $amount): self
    {
        if ($this->cents < $amount->cents()) {
            throw new DomainException('Balance is not enough');
        }

        return new self($this->cents - $amount->cents());
    }

    public function covers(Money $amount): bool
    {
        return $this->cents >= $amount->cents();
    }
}
