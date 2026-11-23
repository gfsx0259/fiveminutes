<?php

declare(strict_types=1);

namespace App\Api\Form;

use Yiisoft\Input\Http\AbstractInput;
use Yiisoft\Input\Http\Attribute\Data\FromBody;
use Yiisoft\Validator\Rule\Required;

#[FromBody]
final class ShelfAgePolicyForm extends AbstractInput
{
    public function __construct(
        #[Required]
        /** @var array<string, mixed> */
        public readonly array $shelf_age_policy = [],
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function payload(): array
    {
        return $this->shelf_age_policy;
    }
}