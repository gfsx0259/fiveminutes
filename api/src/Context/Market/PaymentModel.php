<?php

declare(strict_types=1);

namespace App\Context\Market;

enum PaymentModel: string
{
    case Cpl = 'cpl';
    case Cpa = 'cpa';
}
