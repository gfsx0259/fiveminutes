<?php

declare(strict_types=1);

use Psr\Log\LoggerInterface;
use Yiisoft\Log\Logger;
use Yiisoft\Log\StreamTarget;

return [
    LoggerInterface::class => static fn(): Logger => new Logger([new StreamTarget()->setExportInterval(1)])
        ->setFlushInterval(1),
];
