<?php

declare(strict_types=1);

namespace App\Context\Wallet;

use App\Orm\Repository;

/**
 * @extends Repository<WalletSettlementClaim>
 */
class WalletSettlementClaimRepository extends Repository
{
    public function exists(string $leadUuid, SettlementKind $kind): bool
    {
        $found = $this->findOne(['leadUuid' => $leadUuid, 'kind' => $kind->value]);

        return $found instanceof WalletSettlementClaim;
    }
}