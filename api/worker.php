<?php

declare(strict_types=1);

use App\Environment;
use Yiisoft\ErrorHandler\Renderer\JsonRenderer;
use Yiisoft\Log\Logger;
use Yiisoft\Log\StreamTarget;
use Yiisoft\Yii\Runner\FrankenPHP\FrankenPHPApplicationRunner;

ini_set('display_errors', 'stderr');

require_once __DIR__ . '/src/autoload.php';

new FrankenPHPApplicationRunner(
    rootPath: __DIR__,
    debug: Environment::appDebug(),
    checkEvents: Environment::appDebug(),
    environment: Environment::appEnv(),
    temporaryErrorHandler: new Yiisoft\ErrorHandler\ErrorHandler(
        new Logger([new StreamTarget()]),
        new JsonRenderer(),
    ),
)->run();
