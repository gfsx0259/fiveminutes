<?php

declare(strict_types=1);

namespace App\Context\Market;

use InvalidArgumentException;

final readonly class StockPickPreference
{
    public function __construct(
        public StockPickPriority $priority,
        public ?string $zoneId = null,
    ) {
        if ($zoneId !== null && !in_array($zoneId, ShelfAgeZone::IDS, true)) {
            throw new InvalidArgumentException('Unknown shelf zone');
        }
        if ($zoneId === ShelfAgeZone::LIVE) {
            throw new InvalidArgumentException('Live zone is not sellable on shelf');
        }
    }

    public static function default(): self
    {
        return new self(StockPickPriority::OldestFirst, null);
    }

    public static function fromRequest(string $priorityRaw, string $zoneRaw): self
    {
        $zone = trim($zoneRaw);
        if ($zone === '') {
            return new self(StockPickPriority::parse($priorityRaw), null);
        }

        return new self(StockPickPriority::parse($priorityRaw), $zone);
    }

    public static function fromStored(?string $priority, ?string $zone): self
    {
        if ($priority === null && ($zone === null || $zone === '')) {
            return self::default();
        }

        return new self(
            StockPickPriority::parse($priority ?? ''),
            $zone === null || $zone === '' ? null : $zone,
        );
    }

    /**
     * @return array{pickPriority: string, pickZone: string|null}
     */
    public function toArray(): array
    {
        return [
            'pickPriority' => $this->priority->value,
            'pickZone' => $this->zoneId,
        ];
    }
}