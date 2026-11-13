<?php

declare(strict_types=1);

use App\Command\ClickHouseMigrateCommand;
use App\Command\OpenChaseCommand;

return [
    'yiisoft/yii-console' => [
        'commands' => [
            'clickhouse:migrate' => ClickHouseMigrateCommand::class,
            'app:open-chase' => OpenChaseCommand::class,
        ],
    ],
];
