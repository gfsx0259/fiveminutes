<?php

declare(strict_types=1);

namespace App\Context\Team;

use InvalidArgumentException;

final class BudgetExceededException extends InvalidArgumentException
{
    public function __construct(TeamUnit $unit, int $leftCents)
    {
        parent::__construct(sprintf(
            'The %s budget is not enough for this order, %s left',
            $unit->getName(),
            '$' . number_format(max(0, $leftCents) / 100, 2),
        ));
    }
}
