<?php

declare(strict_types=1);

namespace App\Value;

use InvalidArgumentException;

final readonly class Criterion
{
    private string $value;

    public function __construct(string $value, string $label)
    {
        $value = strtolower(trim($value));
        if ($value === '' || strlen($value) > 64) {
            throw new InvalidArgumentException($label . ' must be 1 to 64 characters');
        }
        $this->value = $value;
    }

    public function toString(): string
    {
        return $this->value;
    }
}
