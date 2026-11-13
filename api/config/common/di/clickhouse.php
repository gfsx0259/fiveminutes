<?php

declare(strict_types=1);

use Beeterty\ClickHouse\Client;
use Beeterty\ClickHouse\Config;

return [
    Client::class => static fn(): Client => new Client(new Config(
        host: $_ENV['RIDER_CLICKHOUSE_HOST'],
        port: (int) $_ENV['RIDER_CLICKHOUSE_PORT'],
        database: $_ENV['RIDER_CLICKHOUSE_DB'],
        username: $_ENV['RIDER_CLICKHOUSE_USER'],
        password: $_ENV['RIDER_CLICKHOUSE_PASSWORD'],
    )),
];
