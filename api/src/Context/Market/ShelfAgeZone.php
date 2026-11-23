<?php

declare(strict_types=1);

namespace App\Context\Market;

use InvalidArgumentException;

final readonly class ShelfAgeZone
{
    public const string LIVE = 'live';
    public const string FRESH = 'fresh';
    public const string GOOD = 'good';
    public const string EXPIRED = 'expired';

    /** @var list<string> */
    public const array IDS = [self::LIVE, self::FRESH, self::GOOD, self::EXPIRED];

    public function __construct(
        public string $id,
        public string $label,
        public int $startMinutes,
        public ?int $endMinutes,
        public int $discountPercent,
        public bool $sellableOnShelf,
    ) {}

    /**
     * @param array<string, mixed> $data
     */
    public static function fromArray(array $data): self
    {
        $id = (string) ($data['id'] ?? '');
        $label = trim((string) ($data['label'] ?? ''));
        if (!in_array($id, self::IDS, true)) {
            throw new InvalidArgumentException('Unknown shelf zone id');
        }
        if ($label === '') {
            throw new InvalidArgumentException('Shelf zone label is empty');
        }

        return new self(
            $id,
            $label,
            (int) ($data['startMinutes'] ?? 0),
            array_key_exists('endMinutes', $data) && $data['endMinutes'] !== null
                ? (int) $data['endMinutes']
                : null,
            (int) ($data['discountPercent'] ?? 0),
            (bool) ($data['sellableOnShelf'] ?? true),
        );
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'id' => $this->id,
            'label' => $this->label,
            'startMinutes' => $this->startMinutes,
            'endMinutes' => $this->endMinutes,
            'discountPercent' => $this->discountPercent,
            'sellableOnShelf' => $this->sellableOnShelf,
        ];
    }
}