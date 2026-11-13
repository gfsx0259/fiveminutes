<?php

declare(strict_types=1);

use App\Listener\CycleOrmHeapCleaner;
use Yiisoft\Yii\Http\Event\AfterRequest;

return [
    AfterRequest::class => [
        CycleOrmHeapCleaner::class,
    ],
];
