<?php

declare(strict_types=1);

use App\Command\ClickHouseMigrateCommand;
use App\Command\LiveDayInjectCommand;
use App\Command\MatchLeadsCommand;
use App\Command\SettleLeadsCommand;
use App\Command\DevSeedCommand;
use App\Command\DevShiftLeadShelfAgeCommand;
use App\Command\DevStockCommand;

return [
    'yiisoft/yii-console' => [
        'commands' => [
            'clickhouse:migrate' => ClickHouseMigrateCommand::class,
            'lead:match' => MatchLeadsCommand::class,
            'wallet:settle' => SettleLeadsCommand::class,
            'live-day:inject' => LiveDayInjectCommand::class,
            'dev:seed' => DevSeedCommand::class,
            'dev:shift-shelf-age' => DevShiftLeadShelfAgeCommand::class,
            'dev:stock' => DevStockCommand::class,
        ],
    ],
];
