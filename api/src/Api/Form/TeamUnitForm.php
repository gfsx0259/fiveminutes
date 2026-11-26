<?php

declare(strict_types=1);

namespace App\Api\Form;

use Yiisoft\Input\Http\AbstractInput;
use Yiisoft\Input\Http\Attribute\Data\FromBody;
use Yiisoft\Validator\Rule\Regex;
use Yiisoft\Validator\Rule\Required;
use Yiisoft\Validator\Rule\StringValue;

#[FromBody]
final class TeamUnitForm extends AbstractInput
{
    public function __construct(
        #[Required(message: 'Name is required', notPassedMessage: 'Name is required')]
        #[StringValue]
        public readonly string $name = '',
        #[Regex(pattern: '/^[0-9a-f-]{36}$/i', message: 'Parent team must be a UUID', skipOnEmpty: true)]
        public readonly ?string $parent_id = null,
    ) {}

    public function name(): string
    {
        return $this->name;
    }

    public function parentId(): ?string
    {
        return $this->parent_id === '' ? null : $this->parent_id;
    }
}
