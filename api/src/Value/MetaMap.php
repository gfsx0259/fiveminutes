<?php

declare(strict_types=1);

namespace App\Value;

use InvalidArgumentException;
use stdClass;

/** Flat string map. Key order does not matter: equality uses a sorted JSON object. */
final readonly class MetaMap
{
    public const string EMPTY = '{}';

    /**
     * @param array<string, string> $pairs
     */
    private function __construct(private array $pairs) {}

    public static function from(mixed $value): self
    {
        if ($value === null || $value === '' || $value === []) {
            return new self([]);
        }
        if (is_string($value)) {
            $decoded = json_decode($value, true);
            if (!is_array($decoded)) {
                throw new InvalidArgumentException('Meta must be a map of fields');
            }
            $value = $decoded;
        }
        if ($value instanceof stdClass) {
            $value = (array) $value;
        }
        if (!is_array($value)) {
            throw new InvalidArgumentException('Meta must be a map of fields');
        }
        if ($value === []) {
            return new self([]);
        }
        if (array_is_list($value)) {
            throw new InvalidArgumentException('Meta must be a map of fields');
        }

        $pairs = [];
        foreach ($value as $key => $item) {
            $name = trim((string) $key);
            if ($name === '' || strlen($name) > 64) {
                throw new InvalidArgumentException('Meta keys must be 1 to 64 characters');
            }
            if (is_bool($item)) {
                $text = $item ? 'true' : 'false';
            } elseif (is_int($item)) {
                $text = (string) $item;
            } elseif (is_string($item)) {
                $text = trim($item);
            } else {
                throw new InvalidArgumentException('Meta values must be strings');
            }
            if (strlen($text) > 120) {
                throw new InvalidArgumentException('Meta values must be 120 characters or fewer');
            }
            $pairs[$name] = $text;
        }
        if (count($pairs) > 32) {
            throw new InvalidArgumentException('Meta can hold up to 32 fields');
        }
        ksort($pairs, SORT_STRING);
        if (strlen(json_encode($pairs, JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)) > 2000) {
            throw new InvalidArgumentException('Meta is too large');
        }

        return new self($pairs);
    }

    /**
     * @return array<string, string>
     */
    public function pairs(): array
    {
        return $this->pairs;
    }

    public function isEmpty(): bool
    {
        return $this->pairs === [];
    }

    public function canonical(): string
    {
        if ($this->pairs === []) {
            return self::EMPTY;
        }

        return json_encode($this->pairs, JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    }

    public function forJson(): array|stdClass
    {
        return $this->pairs === [] ? new stdClass() : $this->pairs;
    }
}
