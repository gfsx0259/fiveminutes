<?php

declare(strict_types=1);

namespace App\Context\User;

use RuntimeException;

final class IdentityAlreadyLinked extends RuntimeException
{
}
