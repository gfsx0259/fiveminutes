<?php

declare(strict_types=1);

// Do not edit. Content will be replaced.
return [
    '/' => [
        'di-web' => [
            'yiisoft/data-response' => [
                'config/di-web.php',
            ],
            'yiisoft/input-http' => [
                'config/di-web.php',
            ],
            'yiisoft/router-fastroute' => [
                'config/di-web.php',
            ],
            'yiisoft/error-handler' => [
                'config/di-web.php',
            ],
            'yiisoft/request-provider' => [
                'config/di-web.php',
            ],
            'yiisoft/yii-event' => [
                'config/di-web.php',
            ],
            '/' => [
                '$di',
                'web/di/*.php',
            ],
        ],
        'params' => [
            'yiisoft/data-response' => [
                'config/params.php',
            ],
            'yiisoft/log-target-file' => [
                'config/params.php',
            ],
            'yiisoft/router-fastroute' => [
                'config/params.php',
            ],
            'yiisoft/yii-cycle' => [
                'config/params.php',
            ],
            'yiisoft/aliases' => [
                'config/params.php',
            ],
            'yiisoft/router' => [
                'config/params.php',
            ],
            'yiisoft/validator' => [
                'config/params.php',
            ],
            'yiisoft/translator' => [
                'config/params.php',
            ],
            '/' => [
                'common/params.php',
            ],
        ],
        'params-web' => [
            'yiisoft/input-http' => [
                'config/params-web.php',
            ],
            'yiisoft/yii-event' => [
                'config/params-web.php',
            ],
            '/' => [
                '$params',
                'web/params.php',
            ],
        ],
        'di' => [
            'yiisoft/log-target-file' => [
                'config/di.php',
            ],
            'yiisoft/router-fastroute' => [
                'config/di.php',
            ],
            'yiisoft/yii-cycle' => [
                'config/di.php',
            ],
            'yiisoft/aliases' => [
                'config/di.php',
            ],
            'yiisoft/router' => [
                'config/di.php',
            ],
            'yiisoft/hydrator' => [
                'config/di.php',
            ],
            'yiisoft/validator' => [
                'config/di.php',
            ],
            'yiisoft/yii-event' => [
                'config/di.php',
            ],
            'yiisoft/translator' => [
                'config/di.php',
            ],
            '/' => [
                'common/di/*.php',
            ],
        ],
        'di-console' => [
            'yiisoft/yii-cycle' => [
                'config/di-console.php',
            ],
            'yiisoft/yii-console' => [
                'config/di-console.php',
            ],
            'yiisoft/yii-event' => [
                'config/di-console.php',
            ],
            '/' => [
                '$di',
                'console/di/*.php',
            ],
        ],
        'di-delegates' => [
            'yiisoft/yii-cycle' => [
                'config/di-delegates.php',
            ],
            '/' => [],
        ],
        'events-console' => [
            'yiisoft/yii-cycle' => [
                'config/events-console.php',
            ],
            'yiisoft/log' => [
                'config/events-console.php',
            ],
            'yiisoft/yii-console' => [
                'config/events-console.php',
            ],
            '/' => [
                '$events',
            ],
        ],
        'events-web' => [
            'yiisoft/middleware-dispatcher' => [
                'config/events-web.php',
            ],
            'yiisoft/request-provider' => [
                'config/events-web.php',
            ],
            'yiisoft/log' => [
                'config/events-web.php',
            ],
            '/' => [
                '$events',
                'web/events.php',
            ],
        ],
        'params-console' => [
            'yiisoft/yii-console' => [
                'config/params-console.php',
            ],
            'yiisoft/yii-event' => [
                'config/params-console.php',
            ],
            '/' => [
                '$params',
                'console/params.php',
            ],
        ],
        'di-delegates-console' => [
            '/' => [
                '$di-delegates',
            ],
        ],
        'di-delegates-web' => [
            '/' => [
                '$di-delegates',
            ],
        ],
        'di-providers' => [
            '/' => [],
        ],
        'di-providers-console' => [
            '/' => [
                '$di-providers',
            ],
        ],
        'di-providers-web' => [
            '/' => [
                '$di-providers',
            ],
        ],
        'events' => [
            '/' => [],
        ],
        'bootstrap' => [
            '/' => [],
        ],
        'bootstrap-console' => [
            '/' => [
                '$bootstrap',
            ],
        ],
        'bootstrap-web' => [
            '/' => [
                '$bootstrap',
            ],
        ],
        'routes' => [
            '/' => [
                'common/routes.php',
            ],
        ],
    ],
    'dev' => [
        'params' => [
            '/' => [
                'environments/dev/params.php',
            ],
        ],
    ],
    'prod' => [
        'params' => [
            '/' => [
                'environments/prod/params.php',
            ],
        ],
    ],
    'test' => [
        'params' => [
            '/' => [
                'environments/test/params.php',
            ],
        ],
    ],
];
