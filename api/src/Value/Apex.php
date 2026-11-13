<?php

declare(strict_types=1);

namespace App\Value;

use InvalidArgumentException;

final readonly class Apex
{
    private string $value;

    public function __construct(string $value)
    {
        $value = strtolower(trim($value));
        if ($value === '' || preg_match('/^[a-z0-9]([a-z0-9-]*[a-z0-9])?(\.[a-z0-9]([a-z0-9-]*[a-z0-9])?)+$/', $value) !== 1) {
            throw new InvalidArgumentException('Apex must be a domain name, for example chase.io');
        }

        $this->value = $value;
    }

    public function toString(): string
    {
        return $this->value;
    }
}
