<?php

declare(strict_types=1);

namespace App\Api\Form;

use DateTimeImmutable;
use DateTimeZone;
use InvalidArgumentException;
use Yiisoft\Input\Http\AbstractInput;
use Yiisoft\Input\Http\Attribute\Data\FromBody;
use Yiisoft\Validator\Result;
use Yiisoft\Validator\Rule\Callback;
use Yiisoft\Validator\Rule\In;
use Yiisoft\Validator\Rule\Integer;
use Yiisoft\Validator\Rule\Regex;
use Yiisoft\Validator\Rule\Required;
use Yiisoft\Validator\ValidationContext;

#[FromBody]
#[Callback(method: 'kindAndDate', skipOnEmpty: false)]
final class OfferForm extends AbstractInput
{
    public function __construct(
        #[Required(message: 'Preset is required', notPassedMessage: 'Preset is required')]
        #[Regex(pattern: '/^[0-9a-fA-F-]{36}$/', message: 'Preset is required')]
        public readonly string $preset_id = '',
        #[Required(message: 'Geo must be a country code, like IT', notPassedMessage: 'Geo must be a country code, like IT')]
        #[Regex(pattern: '/^[A-Z]{2}$/', message: 'Geo must be a country code, like IT')]
        public readonly string $geo = '',
        #[Required]
        #[Integer(
            min: 1,
            incorrectInputMessage: 'Volume must be a positive whole number',
            notNumberMessage: 'Volume must be a positive whole number',
            lessThanMinMessage: 'Volume must be a positive whole number',
        )]
        public readonly int $volume = 0,
        #[Required(message: 'Kind must be day or stock', notPassedMessage: 'Kind must be day or stock')]
        #[In(values: ['day', 'stock'], message: 'Kind must be day or stock')]
        public readonly string $kind = '',
        public readonly string $service_on = '',
    ) {}

    public function kindAndDate(mixed $value, Callback $rule, ValidationContext $context): Result
    {
        $result = new Result();
        if ($this->kind === 'day' && preg_match('/^\d{4}-\d{2}-\d{2}$/', $this->service_on) !== 1) {
            $result->addError('Date must look like 2026-11-16', valuePath: ['service_on']);
        }
        if ($this->kind === 'stock' && $this->service_on !== '') {
            $result->addError('A stock offer has no date', valuePath: ['service_on']);
        }

        return $result;
    }

    public function presetId(): string
    {
        return $this->preset_id;
    }

    public function geo(): string
    {
        return $this->geo;
    }

    public function volume(): int
    {
        return $this->volume;
    }

    public function stock(): bool
    {
        return $this->kind === 'stock';
    }

    public function serviceOn(): ?DateTimeImmutable
    {
        if ($this->stock()) {
            return null;
        }
        $day = DateTimeImmutable::createFromFormat('!Y-m-d', $this->service_on, new DateTimeZone('UTC'));
        if ($day === false) {
            throw new InvalidArgumentException('Date must look like 2026-11-16');
        }

        return $day;
    }
}
