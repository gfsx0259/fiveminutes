<?php

declare(strict_types=1);

namespace App\Api\Form;

use App\Context\Market\PricingModel;
use DateTimeImmutable;
use DateTimeZone;
use InvalidArgumentException;
use Yiisoft\Input\Http\AbstractInput;
use Yiisoft\Input\Http\Attribute\Data\FromBody;
use Yiisoft\Validator\Rule\Date\Date;
use Yiisoft\Validator\Rule\InEnum;
use Yiisoft\Validator\Rule\Integer;
use Yiisoft\Validator\Rule\Regex;
use Yiisoft\Validator\Rule\Required;

#[FromBody]
final class SlotForm extends AbstractInput
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
        #[Required(message: 'Deadline must be a date, like 2026-11-14', notPassedMessage: 'Deadline must be a date, like 2026-11-14')]
        #[Date(format: 'php:Y-m-d', incorrectInputMessage: 'Deadline must be a date, like 2026-11-14')]
        public readonly string $deadline = '',
    ) {}

    public function deadlineAt(): DateTimeImmutable
    {
        $day = DateTimeImmutable::createFromFormat('!Y-m-d', $this->deadline, new DateTimeZone('UTC'));
        if ($day === false) {
            throw new InvalidArgumentException('Deadline must be a date, like 2026-11-14');
        }

        return $day->setTime(23, 59, 59);
    }
}
