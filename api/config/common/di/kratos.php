<?php

declare(strict_types=1);

use App\Context\Identity\KratosSessions;
use GuzzleHttp\Client;

return [
    KratosSessions::class => static fn(): KratosSessions => new KratosSessions(
        new Client([
            'http_errors' => false,
            'timeout' => 5,
        ]),
        $_ENV['RIDER_KRATOS_PUBLIC'],
    ),
];
