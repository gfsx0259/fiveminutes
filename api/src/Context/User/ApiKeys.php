<?php

declare(strict_types=1);

namespace App\Context\User;

final readonly class ApiKeys
{
    public function issue(): ApiKey
    {
        return new ApiKey('rk_' . bin2hex(random_bytes(32)));
    }
}
