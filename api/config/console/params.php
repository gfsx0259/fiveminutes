<?php

declare(strict_types=1);

use App\Command\ClickHouseMigrateCommand;
use App\Command\LiveDayInjectCommand;
use App\Command\MatchLeadsCommand;
use App\Command\SettleLeadsCommand;
use App\Command\DevSeedCommand;
use App\Command\DevShiftLeadShelfAgeCommand;
use App\Command\DevStockCommand;
use App\Command\DevTeamCommand;
use App\Command\TeamBudgetAlertsCommand;

return [
    'yiisoft/yii-console' => [
        'commands' => [
            'clickhouse:migrate' => ClickHouseMigrateCommand::class,
            'lead:match' => MatchLeadsCommand::class,
            'wallet:settle' => SettleLeadsCommand::class,
            'live-day:inject' => LiveDayInjectCommand::class,
            'team:budget-alerts' => TeamBudgetAlertsCommand::class,
            'dev:seed' => DevSeedCommand::class,
            'dev:shift-shelf-age' => DevShiftLeadShelfAgeCommand::class,
            'dev:stock' => DevStockCommand::class,
            'dev:team' => DevTeamCommand::class,
        ],
    ],
];
