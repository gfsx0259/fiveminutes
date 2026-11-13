<?php

declare(strict_types=1);

namespace App\Value;

final class Volume
{
    private int $value;

    public function __construct(int $value)
    {
        if ($value <= 0) {
            throw new \InvalidArgumentException('Volume must be positive');
        }

        $this->value = $value;
    }

    public function toInt(): int
    {
        return $this->value;
    }
}
