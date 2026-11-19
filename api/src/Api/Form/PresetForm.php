<?php

declare(strict_types=1);

namespace App\Api\Form;

use Yiisoft\Input\Http\AbstractInput;
use Yiisoft\Input\Http\Attribute\Data\FromBody;
use Yiisoft\Validator\Rule\In;
use Yiisoft\Validator\Rule\Integer;
use Yiisoft\Validator\Rule\Length;
use Yiisoft\Validator\Rule\Regex;
use Yiisoft\Validator\Rule\Required;

#[FromBody]
final class PresetForm extends AbstractInput
{
    public function __construct(
        #[Required(message: 'Country is required', notPassedMessage: 'Country is required')]
        #[Regex(pattern: '/^[A-Z]{2}$/', message: 'Country must be an ISO code, like DE')]
        public readonly string $country = '',
        #[Required(message: 'Traffic source is required', notPassedMessage: 'Traffic source is required')]
        #[In(values: ['facebook', 'google', 'tiktok'], message: 'Traffic source must be facebook, google or tiktok')]
        public readonly string $traffic_source = '',
        #[Length(max: 64, greaterThanMaxMessage: 'Age must be 64 characters or fewer')]
        public readonly string $age = '',
        #[Required(message: 'Gender is required', notPassedMessage: 'Gender is required')]
        #[In(values: ['m', 'f', 'all'], message: 'Gender must be male, female or all')]
        public readonly string $gender = '',
        #[Required(message: 'Vertical is required', notPassedMessage: 'Vertical is required')]
        #[In(
            values: ['gambling', 'betting', 'crypto', 'forex', 'dating', 'nutra', 'finance', 'other'],
            message: 'Vertical is not supported',
        )]
        public readonly string $vertical = '',
        #[Required(message: 'Payment model is required', notPassedMessage: 'Payment model is required')]
        #[In(values: ['cpl', 'cpa'], message: 'Payment model must be cpl or cpa')]
        public readonly string $payment_model = '',
        #[Required]
        #[Integer(
            min: 0,
            max: 100,
            incorrectInputMessage: 'CR must be a whole number from 0 to 100',
            notNumberMessage: 'CR must be a whole number from 0 to 100',
            lessThanMinMessage: 'CR must be a whole number from 0 to 100',
            greaterThanMaxMessage: 'CR must be a whole number from 0 to 100',
        )]
        public readonly int $cr = 0,
        #[Required]
        #[Integer(
            min: 1,
            incorrectInputMessage: 'Floor price must be a positive whole number of dollars',
            notNumberMessage: 'Floor price must be a positive whole number of dollars',
            lessThanMinMessage: 'Floor price must be a positive whole number of dollars',
        )]
        public readonly int $floor_price = 0,
        #[Required]
        #[Integer(
            min: 1,
            incorrectInputMessage: 'Stock price must be a positive whole number of dollars',
            notNumberMessage: 'Stock price must be a positive whole number of dollars',
            lessThanMinMessage: 'Stock price must be a positive whole number of dollars',
        )]
        public readonly int $stock_price = 0,
    ) {}

    public function country(): string
    {
        return $this->country;
    }

    public function trafficSource(): string
    {
        return $this->traffic_source;
    }

    public function age(): string
    {
        return trim($this->age);
    }

    public function gender(): string
    {
        return $this->gender;
    }

    public function vertical(): string
    {
        return $this->vertical;
    }

    public function paymentModel(): string
    {
        return $this->payment_model;
    }

    public function cr(): int
    {
        return $this->cr;
    }

    public function floorPriceCents(): int
    {
        return $this->floor_price * 100;
    }

    public function stockPriceCents(): int
    {
        return $this->stock_price * 100;
    }
}