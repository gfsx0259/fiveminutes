<?php

declare(strict_types=1);

namespace App\Context\Team;

use DomainException;

final class MemberMissingException extends DomainException
{
    public function __construct()
    {
        parent::__construct('Team member was not found');
    }
}
