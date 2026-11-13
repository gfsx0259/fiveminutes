<?php

declare(strict_types=1);

namespace App\Context\User;

use InvalidArgumentException;

final readonly class ApiKey
{
    public function __construct(private string $value)
    {
        if (preg_match('/^rk_[a-f0-9]{64}$/', $this->value) !== 1) {
            throw new InvalidArgumentException('Api key is invalid');
        }
    }

    public function value(): string
    {
        return $this->value;
    }
}
