<?php

declare(strict_types=1);

use Ory\Client\Api\FrontendApi;
use Ory\Client\Configuration;

$config = Configuration::getDefaultConfiguration()->setHost($_ENV['RIDER_KRATOS_PUBLIC']);

return [
    FrontendApi::class => [
        '__construct()' => [
            'config' => $config,
        ],
    ],
];
