<?php

declare(strict_types=1);

namespace App\Api\Form;

use Yiisoft\Input\Http\AbstractInput;
use Yiisoft\Input\Http\Attribute\Data\FromBody;
use Yiisoft\Validator\Rule\BooleanValue;
use Yiisoft\Validator\Rule\Regex;
use Yiisoft\Validator\Rule\Required;

#[FromBody]
final class TeamHandOverForm extends AbstractInput
{
    public function __construct(
        #[Required(message: 'Pick the new owner', notPassedMessage: 'Pick the new owner')]
        #[Regex(pattern: '/^[0-9a-f-]{36}$/i', message: 'Pick the new owner')]
        public readonly string $member_id = '',
        #[BooleanValue]
        public readonly bool $keep_former_owner = true,
    ) {}

    public function memberId(): string
    {
        return $this->member_id;
    }

    public function keepFormerOwner(): bool
    {
        return $this->keep_former_owner;
    }
}
