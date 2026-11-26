<?php

declare(strict_types=1);

namespace App\Api\Form;

use Yiisoft\Input\Http\AbstractInput;
use Yiisoft\Input\Http\Attribute\Data\FromBody;
use Yiisoft\Validator\Rule\In;
use Yiisoft\Validator\Rule\Integer;
use Yiisoft\Validator\Rule\Required;

#[FromBody]
final class ExportForm extends AbstractInput
{
    public function __construct(
        #[Required(message: 'Export kind is required', notPassedMessage: 'Export kind is required')]
        #[In(values: ['leads', 'orders', 'reports', 'transactions'], message: 'Unknown export')]
        public readonly string $kind = '',
        #[Integer(min: 0, incorrectInputMessage: 'Rows must be a number', notNumberMessage: 'Rows must be a number', lessThanMinMessage: 'Rows must be a number')]
        public readonly int $rows = 0,
    ) {}

    public function kind(): string
    {
        return $this->kind;
    }

    public function rows(): int
    {
        return $this->rows;
    }
}
