<?php

declare(strict_types=1);

use App\Command\ClickHouseMigrateCommand;
use App\Command\LiveDayInjectCommand;
use App\Command\MatchLeadsCommand;
use App\Command\SettleLeadsCommand;
use App\Command\DevMarshallMarketCommand;
use App\Command\DevSeedCommand;
use App\Command\DevShiftLeadShelfAgeCommand;

return [
    'yiisoft/yii-console' => [
        'commands' => [
            'clickhouse:migrate' => ClickHouseMigrateCommand::class,
            'lead:match' => MatchLeadsCommand::class,
            'wallet:settle' => SettleLeadsCommand::class,
            'live-day:inject' => LiveDayInjectCommand::class,
            'dev:seed' => DevSeedCommand::class,
            'dev:marshall-market' => DevMarshallMarketCommand::class,
            'dev:shift-shelf-age' => DevShiftLeadShelfAgeCommand::class,
        ],
    ],
];
