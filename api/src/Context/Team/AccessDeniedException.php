<?php

declare(strict_types=1);

namespace App\Context\Team;

use DomainException;

final class AccessDeniedException extends DomainException
{
    public function __construct(Permission $permission)
    {
        parent::__construct('You do not have access: ' . $permission->label());
    }
}
