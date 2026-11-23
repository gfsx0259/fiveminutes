<?php

declare(strict_types=1);

namespace App\Api\Form;

use Yiisoft\Input\Http\AbstractInput;
use Yiisoft\Input\Http\Attribute\Data\FromQuery;
use Yiisoft\Validator\Rule\Integer;
use Yiisoft\Validator\Rule\Regex;
use Yiisoft\Validator\Rule\Required;
use Yiisoft\Validator\Rule\StringValue;

#[FromQuery]
final class StockQuoteForm extends AbstractInput
{
    public function __construct(
        #[Required(message: 'Preset is required', notPassedMessage: 'Preset is required')]
        #[Regex(pattern: '/^[0-9a-fA-F-]{36}$/', message: 'Preset is required')]
        public readonly string $preset_id = '',
        #[Required]
        #[Integer(
            min: 1,
            incorrectInputMessage: 'Quantity must be a positive whole number',
            notNumberMessage: 'Quantity must be a positive whole number',
            lessThanMinMessage: 'Quantity must be a positive whole number',
        )]
        public readonly int $quantity = 0,
        #[StringValue]
        public readonly string $stock_gender = '',
        #[StringValue]
        public readonly string $pick_priority = '',
        #[StringValue]
        public readonly string $pick_zone = '',
    ) {}

    public function presetId(): string
    {
        return $this->preset_id;
    }

    public function quantity(): int
    {
        return $this->quantity;
    }

    public function stockLeadGender(): ?string
    {
        $gender = trim($this->stock_gender);

        return $gender === '' ? null : $gender;
    }

    public function stockPickPreference(): \App\Context\Market\StockPickPreference
    {
        return \App\Context\Market\StockPickPreference::fromRequest($this->pick_priority, $this->pick_zone);
    }
}