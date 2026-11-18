<?php

declare(strict_types=1);

namespace App\Context\Wallet;

enum SettlementEvent: string
{
    case CplDelivered = 'cpl_delivered';
    case CpaSold = 'cpa_sold';
}