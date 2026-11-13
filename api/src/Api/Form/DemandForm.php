<?php

declare(strict_types=1);

namespace App\Api\Form;

use App\Context\Market\PricingModel;
use DateTimeImmutable;
use DateTimeZone;
use InvalidArgumentException;
use Yiisoft\Input\Http\AbstractInput;
use Yiisoft\Input\Http\Attribute\Data\FromBody;
use Yiisoft\Validator\Result;
use Yiisoft\Validator\Rule\Callback;
use Yiisoft\Validator\ValidationContext;
use Yiisoft\Validator\Rule\Date\Date;
use Yiisoft\Validator\Rule\Date\Time;
use Yiisoft\Validator\Rule\InEnum;
use Yiisoft\Validator\Rule\Integer;
use Yiisoft\Validator\Rule\Regex;
use Yiisoft\Validator\Rule\Required;

#[FromBody]
#[Callback(method: 'callCenterClosesAfterOpen', skipOnEmpty: false)]
final class DemandForm extends AbstractInput
{
    use OfferInput;

    public function __construct(
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
        #[Required]
        #[Integer(
            min: 1,
            incorrectInputMessage: 'Price must be a positive whole number of dollars',
            notNumberMessage: 'Price must be a positive whole number of dollars',
            lessThanMinMessage: 'Price must be a positive whole number of dollars',
        )]
        public readonly int $price = 0,
        #[Required(message: 'Pricing model must be cpl or cpa', notPassedMessage: 'Pricing model must be cpl or cpa')]
        #[InEnum(class: PricingModel::class, message: 'Pricing model must be cpl or cpa')]
        public readonly string $pricing_model = '',
        #[Required(message: 'Date must look like 2026-11-14', notPassedMessage: 'Date must look like 2026-11-14')]
        #[Date(format: 'php:Y-m-d', incorrectInputMessage: 'Date must look like 2026-11-14')]
        public readonly string $service_on = '',
        #[Required(message: 'Opening hour must be HH:MM', notPassedMessage: 'Opening hour must be HH:MM')]
        #[Time(format: 'php:H:i', incorrectInputMessage: 'Opening hour must be HH:MM')]
        public readonly string $opens_at = '',
        #[Required(message: 'Closing hour must be HH:MM', notPassedMessage: 'Closing hour must be HH:MM')]
        #[Time(format: 'php:H:i', incorrectInputMessage: 'Closing hour must be HH:MM')]
        public readonly string $closes_at = '',
    ) {}

    public function callCenterClosesAfterOpen(mixed $value, Callback $rule, ValidationContext $context): Result
    {
        $result = new Result();
        if (preg_match('/^([01]\d|2[0-3]):[0-5]\d$/', $this->opens_at) !== 1) {
            return $result;
        }
        if (preg_match('/^([01]\d|2[0-3]):[0-5]\d$/', $this->closes_at) !== 1) {
            return $result;
        }
        if ($this->opens_at >= $this->closes_at) {
            $result->addError('Call center must close after it opens', valuePath: ['closes_at']);
        }

        return $result;
    }

    public function serviceOn(): DateTimeImmutable
    {
        $day = DateTimeImmutable::createFromFormat('!Y-m-d', $this->service_on, new DateTimeZone('UTC'));
        if ($day === false) {
            throw new InvalidArgumentException('Date must look like 2026-11-14');
        }

        return $day;
    }

    public function opensAt(): string
    {
        return $this->opens_at;
    }

    public function closesAt(): string
    {
        return $this->closes_at;
    }
}
