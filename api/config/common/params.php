<?php

declare(strict_types=1);

use App\Environment;
use Cycle\Database\Config\Postgres\TcpConnectionConfig;
use Cycle\Database\Config\PostgresDriverConfig;
use Cycle\Schema\Provider\FromFilesSchemaProvider;
use Yiisoft\Yii\Cycle\Schema\Provider\FromConveyorSchemaProvider;

return [
    'application' => require __DIR__ . '/application.php',

    'yiisoft/aliases' => [
        'aliases' => require __DIR__ . '/aliases.php',
    ],

    'yiisoft/yii-cycle' => [
        'dbal' => [
            'query-logger' => null,
            'default' => 'default',
            'aliases' => [],
            'databases' => [
                'default' => ['connection' => 'main'],
            ],
            'connections' => [
                'main' => new PostgresDriverConfig(
                    connection: new TcpConnectionConfig(
                        database: $_ENV['RIDER_POSTGRES_DB'],
                        host: $_ENV['RIDER_POSTGRES_HOST'],
                        port: (int) $_ENV['RIDER_POSTGRES_PORT'],
                        user: $_ENV['RIDER_POSTGRES_USER'],
                        password: $_ENV['RIDER_POSTGRES_PASSWORD'],
                    ),
                    schema: 'public',
                ),
            ],
        ],
        'migrations' => [
            'directory' => '@root/migrations',
            'namespace' => 'App\\Migration',
            'table' => 'migrations',
            'safe' => true,
        ],
        'entity-paths' => [
            '@src/Context',
        ],
        'schema-providers' => Environment::isProd()
            ? [FromFilesSchemaProvider::class => FromFilesSchemaProvider::config(files: ['@runtime/cycle-schema.php'])]
            : [FromConveyorSchemaProvider::class],
    ],
];
