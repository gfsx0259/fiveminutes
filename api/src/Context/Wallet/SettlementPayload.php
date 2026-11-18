<?php

declare(strict_types=1);

namespace App\Context\Wallet;

final readonly class SettlementPayload
{
    public function __construct(
        public string $leadUuid,
        public string $orderId,
        public SettlementEvent $kind,
        public int $catalogCents,
        public string $partnerId,
        public string $buyerId,
        public string $organizationId,
    ) {}

    public function groupKey(): string
    {
        return $this->orderId . ':' . $this->partnerId . ':' . $this->buyerId;
    }
}