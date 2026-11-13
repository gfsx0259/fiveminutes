<?php

declare(strict_types=1);

namespace App;

use DateTimeImmutable;

final class Clock
{
    public function now(): DateTimeImmutable
    {
        return new DateTimeImmutable();
    }
}
