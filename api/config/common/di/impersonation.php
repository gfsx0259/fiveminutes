<?php

declare(strict_types=1);

use App\Context\Identity\ImpersonationCookie;

return [
    ImpersonationCookie::class => [
        '__construct()' => [
            'secret' => $_ENV['RIDER_IMPERSONATION_SECRET'] ?? 'dev-impersonation-secret',
        ],
    ],
];