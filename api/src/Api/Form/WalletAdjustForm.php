<?php

declare(strict_types=1);

namespace App\Api\Form;

use Yiisoft\Input\Http\AbstractInput;
use Yiisoft\Input\Http\Attribute\Data\FromBody;
use Yiisoft\Validator\Rule\Integer;

#[FromBody]
final class WalletAdjustForm extends AbstractInput
{
    public function __construct(
        #[Integer(min: 0, max: 100_000_000)]
        public readonly int $amount_cents = 0,
    ) {}

    public function amountCents(): int
    {
        return $this->amount_cents;
    }
}