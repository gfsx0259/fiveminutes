<?php

declare(strict_types=1);

namespace App\Context\Wallet;

use Cycle\Annotated\Annotation\Column;
use Cycle\Annotated\Annotation\Entity;
use DateTimeInterface;

#[Entity(repository: WalletSettlementClaimRepository::class, table: 'wallet_settlement_claims')]
class WalletSettlementClaim
{
    public function __construct(
        #[Column(type: 'uuid', name: 'lead_uuid', primary: true)]
        private readonly string $leadUuid,
        #[Column(type: 'string', primary: true)]
        private readonly string $kind,
        #[Column(type: 'datetime', name: 'created_at', typecast: 'datetime')]
        private readonly DateTimeInterface $createdAt,
    ) {}

    public function getLeadUuid(): string
    {
        return $this->leadUuid;
    }

    public function getKind(): string
    {
        return $this->kind;
    }
}