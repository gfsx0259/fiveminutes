<?php

declare(strict_types=1);

namespace App\Api\Form;

use Yiisoft\Input\Http\AbstractInput;
use Yiisoft\Input\Http\Attribute\Data\FromBody;
use Yiisoft\Validator\Rule\Regex;

#[FromBody]
final class TeamOrderForm extends AbstractInput
{
    public function __construct(
        #[Regex(pattern: '/^[0-9a-f-]{36}$/i', message: 'Team must be a UUID', skipOnEmpty: true)]
        public readonly ?string $team_id = null,
    ) {}

    public function teamId(): ?string
    {
        return $this->team_id === '' ? null : $this->team_id;
    }
}
