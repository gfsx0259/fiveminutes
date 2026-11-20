<?php

declare(strict_types=1);

namespace App\Api\Form;

use Yiisoft\Input\Http\AbstractInput;
use Yiisoft\Input\Http\Attribute\Data\FromBody;
use Yiisoft\Validator\Rule\Regex;
use Yiisoft\Validator\Rule\Required;
use Yiisoft\Validator\Rule\StringValue;

#[FromBody]
final class ImpersonateForm extends AbstractInput
{
    public function __construct(
        #[Required]
        #[StringValue]
        #[Regex(
            pattern: '/^[0-9a-f]{8}-[0-9a-f]{4}-[1-5][0-9a-f]{3}-[89ab][0-9a-f]{3}-[0-9a-f]{12}$/i',
            message: 'User id must be a UUID',
        )]
        public readonly string $userId = '',
    ) {}
}