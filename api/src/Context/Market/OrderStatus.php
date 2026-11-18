<?php

declare(strict_types=1);

namespace App\Context\Market;

enum OrderStatus: string
{
    case InProgress = 'in_progress';
    case Paused = 'paused';
    case Finished = 'finished';
}
