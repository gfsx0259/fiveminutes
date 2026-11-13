<?php

declare(strict_types=1);

use App\Command\ClickHouseMigrateCommand;
use App\Command\MatchLeadsCommand;
use App\Command\DevSeedChaseCommand;

return [
    'yiisoft/yii-console' => [
        'commands' => [
            'clickhouse:migrate' => ClickHouseMigrateCommand::class,
            'lead:match' => MatchLeadsCommand::class,
            'dev:seed-chase' => DevSeedChaseCommand::class,
        ],
    ],
];
