<?php

declare(strict_types=1);

use Ramsey\Uuid\UuidFactory;
use Ramsey\Uuid\UuidFactoryInterface;

return [
    UuidFactoryInterface::class => [
        'class' => UuidFactory::class,
    ],
];
