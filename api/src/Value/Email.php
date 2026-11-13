<?php

declare(strict_types=1);

namespace App\Value;

final class Email
{
    private string $value;

    public function __construct(string $value)
    {
        $value = strtolower(trim($value));
        if (filter_var($value, FILTER_VALIDATE_EMAIL) === false) {
            throw new \InvalidArgumentException('Email is invalid');
        }

        $this->value = $value;
    }

    public function toString(): string
    {
        return $this->value;
    }
}
