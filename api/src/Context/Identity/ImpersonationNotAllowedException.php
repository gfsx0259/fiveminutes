<?php

declare(strict_types=1);

namespace App\Context\Identity;

use DomainException;

final class ImpersonationNotAllowedException extends DomainException
{
    public static function yourself(): self
    {
        return new self('Cannot impersonate yourself');
    }

    public static function admin(): self
    {
        return new self('Cannot impersonate an admin');
    }

    public static function foreignOrganization(): self
    {
        return new self('Cannot impersonate a user outside your organization');
    }

    public static function notAdmin(): self
    {
        return new self('Only an admin can impersonate');
    }
}