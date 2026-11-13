<?php

declare(strict_types=1);

use App\Environment;

// После правок этого файла: composer yii-config-rebuild
return [
    'config-plugin' => [
        'params' => 'common/params.php',
        'params-web' => [
            '$params',
            'web/params.php',
        ],
        'params-console' => [
            '$params',
            'console/params.php',
        ],
        'di' => 'common/di/*.php',
        'di-web' => [
            '$di',
            'web/di/*.php',
        ],
        'di-console' => [
            '$di',
            'console/di/*.php',
        ],
        'di-delegates' => [],
        'di-delegates-console' => '$di-delegates',
        'di-delegates-web' => '$di-delegates',
        'di-providers' => [],
        'di-providers-console' => '$di-providers',
        'di-providers-web' => '$di-providers',
        'events' => [],
        'events-console' => '$events',
        'events-web' => [
            '$events',
            'web/events.php',
        ],
        'bootstrap' => [],
        'bootstrap-console' => '$bootstrap',
        'bootstrap-web' => '$bootstrap',
        'routes' => 'common/routes.php',
    ],
    'config-plugin-environments' => [
        Environment::DEV => [
            'params' => [
                'environments/dev/params.php',
            ],
        ],
        Environment::TEST => [
            'params' => [
                'environments/test/params.php',
            ],
        ],
        Environment::PROD => [
            'params' => [
                'environments/prod/params.php',
            ],
        ],
    ],
    'config-plugin-options' => [
        'source-directory' => 'config',
    ],
];
