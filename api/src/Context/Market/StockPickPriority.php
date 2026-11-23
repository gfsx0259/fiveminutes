<?php

declare(strict_types=1);

namespace App\Context\Market;

enum StockPickPriority: string
{
    case OldestFirst = 'oldest_first';
    case NewestFirst = 'newest_first';

    public static function parse(string $raw): self
    {
        $value = strtolower(trim($raw));

        return self::tryFrom($value) ?? self::OldestFirst;
    }
}