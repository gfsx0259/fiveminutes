<?php

declare(strict_types=1);

namespace App\Context\Wallet;

enum SettlementKind: string
{
    case CplDelivered = 'cpl_delivered';
    case CpaPartnerCharge = 'cpa_partner_charge';
    case CpaApprovedPayout = 'cpa_approved_payout';
}