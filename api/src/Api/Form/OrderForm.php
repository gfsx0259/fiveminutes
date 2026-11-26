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
#[Callback(method: 'kindAndHours', skipOnEmpty: false)]
#[Callback(method: 'dayOrderPrice', skipOnEmpty: false)]
final class OrderForm extends AbstractInput
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
        #[Integer(
            min: 0,
            incorrectInputMessage: 'Price must be a whole number of dollars',
            notNumberMessage: 'Price must be a whole number of dollars',
            lessThanMinMessage: 'Price must be a whole number of dollars',
        )]
        public readonly int $price = 0,
        #[Required(message: 'Model must be CPL or CPA', notPassedMessage: 'Model must be CPL or CPA')]
        #[In(values: ['cpl', 'cpa'], message: 'Model must be CPL or CPA')]
        public readonly string $payment_model = '',
        #[Required(message: 'Kind must be day or stock', notPassedMessage: 'Kind must be day or stock')]
        #[In(values: ['day', 'stock'], message: 'Kind must be day or stock')]
        public readonly string $kind = '',
        public readonly string $service_on = '',
        public readonly string $opens_at = '',
        public readonly string $closes_at = '',
        public readonly string $timezone = '',
        public readonly string $stock_gender = '',
        public readonly string $pick_priority = '',
        public readonly string $pick_zone = '',
        #[Regex(pattern: '/^[0-9a-f-]{36}$/i', message: 'Team must be a UUID', skipOnEmpty: true)]
        public readonly string $team_id = '',
    ) {}

    public function teamId(): ?string
    {
        return $this->team_id === '' ? null : $this->team_id;
    }

    public function kindAndHours(mixed $value, Callback $rule, ValidationContext $context): Result
    {
        $result = new Result();
        if ($this->kind === 'day' && preg_match('/^\d{4}-\d{2}-\d{2}$/', $this->service_on) !== 1) {
            $result->addError('Date must look like 2026-11-16', valuePath: ['service_on']);
        }
        if ($this->kind === 'stock' && $this->service_on !== '') {
            $result->addError('A stock order has no date', valuePath: ['service_on']);
        }
        if ($this->stock()) {
            return $result;
        }
        if (preg_match('/^([01]\d|2[0-3]):[0-5]\d$/', $this->opens_at) !== 1) {
            $result->addError('Opening hour must be HH:MM', valuePath: ['opens_at']);
        }
        if (preg_match('/^([01]\d|2[0-3]):[0-5]\d$/', $this->closes_at) !== 1) {
            $result->addError('Closing hour must be HH:MM', valuePath: ['closes_at']);
        }
        if ($result->isValid() && $this->opens_at >= $this->closes_at) {
            $result->addError('Call center must close after it opens', valuePath: ['closes_at']);
        }
        if (!in_array($this->timezone, timezone_identifiers_list(), true)) {
            $result->addError('Choose a timezone', valuePath: ['timezone']);
        }

        return $result;
    }

    public function dayOrderPrice(mixed $value, Callback $rule, ValidationContext $context): Result
    {
        $result = new Result();
        if ($this->stock()) {
            return $result;
        }
        if ($this->price < 1) {
            $result->addError(
                'Price must be a positive whole number of dollars',
                valuePath: ['price'],
            );
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

    public function priceCents(): int
    {
        return $this->price * 100;
    }

    public function paymentModel(): string
    {
        return $this->payment_model;
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

    public function opensAt(): string
    {
        if ($this->stock() && $this->opens_at === '') {
            return '00:00';
        }

        return $this->opens_at;
    }

    public function closesAt(): string
    {
        if ($this->stock() && $this->closes_at === '') {
            return '23:59';
        }

        return $this->closes_at;
    }

    public function timezone(): string
    {
        if ($this->stock()) {
            return 'UTC';
        }

        return $this->timezone;
    }

    public function stockLeadGender(): ?string
    {
        if (!$this->stock() || $this->stock_gender === '') {
            return null;
        }

        return $this->stock_gender;
    }

    public function stockPickPreference(): \App\Context\Market\StockPickPreference
    {
        if (!$this->stock()) {
            return \App\Context\Market\StockPickPreference::default();
        }

        return \App\Context\Market\StockPickPreference::fromRequest($this->pick_priority, $this->pick_zone);
    }
}
