<?php

declare(strict_types=1);

namespace App\Context\Market;

use InvalidArgumentException;
use ValueError;

final readonly class RotationAxes
{
    /**
     * @param list<RotationAxis> $axes
     */
    public function __construct(public array $axes)
    {
        if ($this->axes === []) {
            throw new InvalidArgumentException('Choose at least one grouping');
        }
        $seen = [];
        foreach ($this->axes as $axis) {
            if (isset($seen[$axis->value])) {
                throw new InvalidArgumentException('Grouping repeats ' . $axis->value);
            }
            $seen[$axis->value] = true;
        }
    }

    public static function default(): self
    {
        return new self([
            RotationAxis::Vertical,
            RotationAxis::Country,
            RotationAxis::PaymentModel,
        ]);
    }

    /**
     * @param list<mixed> $values
     */
    public static function fromList(array $values): self
    {
        $axes = [];
        foreach ($values as $value) {
            if (!is_string($value)) {
                throw new InvalidArgumentException('Grouping must be a list of axis names');
            }
            try {
                $axes[] = RotationAxis::from($value);
            } catch (ValueError) {
                throw new InvalidArgumentException('Unknown grouping ' . $value);
            }
        }

        return new self($axes);
    }

    /**
     * @return list<string>
     */
    public function toList(): array
    {
        return array_map(static fn (RotationAxis $axis): string => $axis->value, $this->axes);
    }
}
