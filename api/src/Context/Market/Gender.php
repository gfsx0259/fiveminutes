<?php

declare(strict_types=1);

namespace App\Context\Market;

enum Gender: string
{
    case Male = 'm';
    case Female = 'f';
    case All = 'all';
}
