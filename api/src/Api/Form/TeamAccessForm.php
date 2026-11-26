<?php

declare(strict_types=1);

namespace App\Api\Form;

use App\Context\Team\Permissions;
use Yiisoft\Input\Http\AbstractInput;
use Yiisoft\Input\Http\Attribute\Data\FromBody;
use Yiisoft\Validator\Rule\Each;
use Yiisoft\Validator\Rule\Regex;
use Yiisoft\Validator\Rule\StringValue;

#[FromBody]
final class TeamAccessForm extends AbstractInput
{
    public function __construct(
        #[Regex(pattern: '/^[0-9a-f-]{36}$/i', message: 'Team must be a UUID', skipOnEmpty: true)]
        public readonly ?string $team_id = null,
        /** @var list<string> */
        #[Each([new StringValue()])]
        public readonly array $permissions = [],
    ) {}

    public function teamId(): ?string
    {
        return $this->team_id === '' ? null : $this->team_id;
    }

    public function permissions(): Permissions
    {
        return new Permissions($this->permissions);
    }
}
