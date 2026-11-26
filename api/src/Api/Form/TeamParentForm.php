<?php

declare(strict_types=1);

namespace App\Api\Form;

use Yiisoft\Input\Http\AbstractInput;
use Yiisoft\Input\Http\Attribute\Data\FromBody;
use Yiisoft\Validator\Rule\Regex;

#[FromBody]
final class TeamParentForm extends AbstractInput
{
    public function __construct(
        #[Regex(pattern: '/^[0-9a-f-]{36}$/i', message: 'Parent team must be a UUID', skipOnEmpty: true)]
        public readonly ?string $parent_id = null,
    ) {}

    public function parentId(): ?string
    {
        return $this->parent_id === '' ? null : $this->parent_id;
    }
}
