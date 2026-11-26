<?php

declare(strict_types=1);

namespace App\Context\Team;

use DomainException;

final class NotTeamLeadException extends DomainException
{
    public function __construct(string $message = 'You cannot manage this part of the team')
    {
        parent::__construct($message);
    }
}
