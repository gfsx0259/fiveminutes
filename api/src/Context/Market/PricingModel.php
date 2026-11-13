<?php

declare(strict_types=1);

namespace App\Context\Market;

enum PricingModel: string
{
    /** Списание в момент доставки лида. */
    case Cpl = 'cpl';

    /** Списание в момент одобрения лида. */
    case Cpa = 'cpa';
}
