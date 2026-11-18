<?php

declare(strict_types=1);

namespace App\Context\Market;

enum TrafficSource: string
{
    case Facebook = 'facebook';
    case Google = 'google';
    case Tiktok = 'tiktok';
}
