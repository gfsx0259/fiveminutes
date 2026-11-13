<?php

declare(strict_types=1);

namespace App\Value;

final class Balance
{
    private int $cents;

    public function __construct(int $cents)
    {
        if ($cents < 0) {
            throw new \InvalidArgumentException('Balance cannot be negative');
        }

        $this->cents = $cents;
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
            throw new \DomainException('Balance is not enough');
        }

        return new self($this->cents - $amount->cents());
    }
}
