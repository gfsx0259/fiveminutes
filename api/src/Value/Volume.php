<?php

declare(strict_types=1);

namespace App\Value;

use InvalidArgumentException;

final readonly class Volume
{
    public function __construct(private int $value)
    {
        if ($this->value <= 0) {
            throw new InvalidArgumentException('Volume must be positive');
        }
    }

    public function toInt(): int
    {
        return $this->value;
    }
}
