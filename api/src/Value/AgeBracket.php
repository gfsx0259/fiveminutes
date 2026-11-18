<?php

declare(strict_types=1);

namespace App\Value;

final readonly class AgeBracket
{
    /**
     * @return array{0: int, 1: int}|null
     */
    public static function span(string $value): ?array
    {
        $value = trim($value);
        if ($value === '') {
            return null;
        }
        if (preg_match('/^(\d+)\s*-\s*(\d+)$/i', $value, $matches) === 1) {
            $min = (int) $matches[1];
            $max = (int) $matches[2];
            if ($min > $max) {
                [$min, $max] = [$max, $min];
            }

            return [$min, $max];
        }
        if (preg_match('/^\d+$/', $value) === 1) {
            $year = (int) $value;

            return [$year, $year];
        }

        return null;
    }

    public static function fits(string $bracket, string $age): bool
    {
        $bracket = trim($bracket);
        $age = trim($age);
        if ($bracket === '') {
            return $age !== '';
        }
        if ($age === '') {
            return false;
        }
        if (strcasecmp($bracket, $age) === 0) {
            return true;
        }
        $preset = self::span($bracket);
        $lead = self::span($age);
        if ($preset === null || $lead === null) {
            return false;
        }

        return $lead[0] >= $preset[0] && $lead[1] <= $preset[1];
    }
}