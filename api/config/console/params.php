<?php

declare(strict_types=1);

use App\Command\ClickHouseMigrateCommand;
use App\Command\MatchLeadsCommand;
use App\Command\SettleLeadsCommand;
use App\Command\DevSeedCommand;

return [
    'yiisoft/yii-console' => [
        'commands' => [
            'clickhouse:migrate' => ClickHouseMigrateCommand::class,
            'lead:match' => MatchLeadsCommand::class,
            'wallet:settle' => SettleLeadsCommand::class,
            'dev:seed' => DevSeedCommand::class,
        ],
    ],
];
