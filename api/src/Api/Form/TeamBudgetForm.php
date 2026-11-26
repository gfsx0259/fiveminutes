<?php

declare(strict_types=1);

namespace App\Api\Form;

use Yiisoft\Input\Http\AbstractInput;
use Yiisoft\Input\Http\Attribute\Data\FromBody;
use App\Context\Team\BudgetPeriod;
use Yiisoft\Validator\Rule\In;
use Yiisoft\Validator\Rule\Integer;

#[FromBody]
final class TeamBudgetForm extends AbstractInput
{
    public function __construct(
        #[Integer(
            min: 0,
            incorrectInputMessage: 'Budget must be a whole number of cents',
            notNumberMessage: 'Budget must be a whole number of cents',
            lessThanMinMessage: 'Budget cannot be negative',
            skipOnEmpty: true,
        )]
        public readonly ?int $budget_cents = null,
        #[In(values: ['total', 'month', 'week'], message: 'Period must be total, month or week')]
        public readonly string $period = 'total',
    ) {}

    public function period(): BudgetPeriod
    {
        return BudgetPeriod::from($this->period);
    }

    public function budgetCents(): ?int
    {
        return $this->budget_cents;
    }
}
