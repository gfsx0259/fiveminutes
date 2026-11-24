<?php

declare(strict_types=1);

namespace App\Context\Market;

use InvalidArgumentException;

final readonly class ShelfAgePolicy
{
    public const int MAX_HORIZON_MINUTES = 2880;

    /**
     * @param list<ShelfAgeZone> $zones
     */
    public function __construct(
        public int $horizonMinutes,
        public array $zones,
    ) {
        $this->validate();
    }

    public static function default(): self
    {
        return new self(self::MAX_HORIZON_MINUTES, [
            new ShelfAgeZone(ShelfAgeZone::LIVE, 'Live', 0, 30, 0, false),
            new ShelfAgeZone(ShelfAgeZone::FRESH, 'Fresh', 30, 120, 10, true),
            new ShelfAgeZone(ShelfAgeZone::GOOD, 'Aged', 120, 360, 30, true),
            new ShelfAgeZone(ShelfAgeZone::EXPIRED, 'Expired', 360, null, 70, true),
        ]);
    }

    /**
     * @param array<string, mixed> $data
     */
    public static function fromArray(array $data): self
    {
        $horizon = (int) ($data['horizonMinutes'] ?? self::MAX_HORIZON_MINUTES);
        $rawZones = $data['zones'] ?? null;
        if (!is_array($rawZones)) {
            throw new InvalidArgumentException('Shelf age policy zones are missing');
        }
        $zones = [];
        foreach ($rawZones as $row) {
            if (!is_array($row)) {
                throw new InvalidArgumentException('Shelf zone must be an object');
            }
            $zones[] = ShelfAgeZone::fromArray($row);
        }

        return new self($horizon, $zones);
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'horizonMinutes' => $this->horizonMinutes,
            'zones' => array_map(static fn (ShelfAgeZone $zone): array => $zone->toArray(), $this->zones),
        ];
    }

    public function zoneById(string $id): ?ShelfAgeZone
    {
        foreach ($this->zones as $zone) {
            if ($zone->id === $id) {
                return $zone;
            }
        }

        return null;
    }

    public function liveZone(): ShelfAgeZone
    {
        $zone = $this->zoneById(ShelfAgeZone::LIVE);
        if ($zone === null) {
            throw new InvalidArgumentException('Live zone is missing');
        }

        return $zone;
    }

    /**
     * @return list<ShelfAgeZone>
     */
    public function sellableZones(): array
    {
        return array_values(array_filter(
            $this->zones,
            static fn (ShelfAgeZone $zone): bool => $zone->sellableOnShelf,
        ));
    }

    private function validate(): void
    {
        if ($this->horizonMinutes < 1 || $this->horizonMinutes > self::MAX_HORIZON_MINUTES) {
            throw new InvalidArgumentException('Shelf horizon must be between 1 and 2880 minutes');
        }
        if (count($this->zones) !== 4) {
            throw new InvalidArgumentException('Shelf age policy must have exactly 4 zones');
        }
        foreach (ShelfAgeZone::IDS as $index => $expectedId) {
            if (($this->zones[$index]->id ?? '') !== $expectedId) {
                throw new InvalidArgumentException('Shelf zones must be ordered live, fresh, good, expired');
            }
        }
        $live = $this->zones[0];
        if ($live->discountPercent !== 0 || $live->sellableOnShelf) {
            throw new InvalidArgumentException('Live zone must have 0% discount and not be sellable on shelf');
        }
        $cursor = 0;
        foreach ($this->zones as $zone) {
            if ($zone->startMinutes !== $cursor) {
                throw new InvalidArgumentException('Shelf zones must be contiguous from minute 0');
            }
            if ($zone->id !== ShelfAgeZone::EXPIRED) {
                if ($zone->endMinutes === null || $zone->endMinutes <= $zone->startMinutes) {
                    throw new InvalidArgumentException('Shelf zone end must be after start');
                }
                $cursor = $zone->endMinutes;
            } else {
                if ($zone->endMinutes !== null) {
                    throw new InvalidArgumentException('Expired zone end must be open');
                }
                $cursor = $this->horizonMinutes;
            }
            if ($zone->sellableOnShelf && $zone->discountPercent < 1) {
                throw new InvalidArgumentException('Sellable zones must have a discount of at least 1%');
            }
        }
    }
}