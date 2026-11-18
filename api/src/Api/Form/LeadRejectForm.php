<?php

declare(strict_types=1);

namespace App\Api\Form;

use Yiisoft\Input\Http\AbstractInput;
use Yiisoft\Input\Http\Attribute\Data\FromBody;
use Yiisoft\Validator\Rule\Required;
use Yiisoft\Validator\Rule\StringValue;

#[FromBody]
final class LeadRejectForm extends AbstractInput
{
    public function __construct(
        #[Required]
        #[StringValue(max: 2000)]
        public readonly string $reason = '',
    ) {}

    public function reason(): string
    {
        return trim($this->reason);
    }
}