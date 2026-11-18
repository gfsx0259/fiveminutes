<?php

declare(strict_types=1);

namespace App\Context\Wallet;

enum TransactionKind: string
{
    case Adjustment = 'adjustment';
    case PartnerLeadCharge = 'partner_lead_charge';
    case BuyerLeadCredit = 'buyer_lead_credit';
    case OrgPlatformFee = 'org_platform_fee';
}