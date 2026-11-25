<?php

declare(strict_types=1);

namespace App\Api\Form;

use Yiisoft\Input\Http\AbstractInput;
use Yiisoft\Input\Http\Attribute\Data\FromBody;
use Yiisoft\Validator\Rule\Required;

#[FromBody]
final class RotationAxesForm extends AbstractInput
{
    public function __construct(
        #[Required]
        /** @var list<mixed> */
        public readonly array $axes = [],
    ) {}

    /**
     * @return list<mixed>
     */
    public function axes(): array
    {
        return $this->axes;
    }
}
