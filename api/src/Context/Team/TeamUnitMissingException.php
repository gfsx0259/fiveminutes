<?php

declare(strict_types=1);

namespace App\Context\Team;

use DomainException;

final class TeamUnitMissingException extends DomainException
{
    public function __construct()
    {
        parent::__construct('Team was not found');
    }
}
