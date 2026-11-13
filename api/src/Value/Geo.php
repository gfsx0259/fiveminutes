<?php

declare(strict_types=1);

namespace App\Value;

use InvalidArgumentException;

final readonly class Geo
{
    private string $value;

    public function __construct(string $value)
    {
        $value = strtoupper(trim($value));
        if (preg_match('/^[A-Z0-9]{2,16}$/', $value) !== 1) {
            throw new InvalidArgumentException('Geo must be 2-16 letters or digits');
        }

        $this->value = $value;
    }

    public function toString(): string
    {
        return $this->value;
    }
}
