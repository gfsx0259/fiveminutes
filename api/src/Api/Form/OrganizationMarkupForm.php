<?php

declare(strict_types=1);

namespace App\Api\Form;

use Yiisoft\Input\Http\AbstractInput;
use Yiisoft\Input\Http\Attribute\Data\FromBody;
use Yiisoft\Validator\Rule\Integer;
use Yiisoft\Validator\Rule\Required;

#[FromBody]
final class OrganizationMarkupForm extends AbstractInput
{
    public function __construct(
        #[Required]
        #[Integer(min: 0, max: 100)]
        public readonly int $markup_percent = 0,
    ) {}

    public function markupPercent(): int
    {
        return $this->markup_percent;
    }
}