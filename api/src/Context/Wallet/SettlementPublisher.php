<?php

declare(strict_types=1);

namespace App\Context\Wallet;

use App\Context\Lead\Lead;
use App\Context\Market\Order;

final readonly class SettlementPublisher
{
    public function __construct(private SettlementQueue $queue) {}

    public function publishMatched(Lead $sold, Order $order): void
    {
        $this->queue->publish(LeadSettlementMessage::fromSoldLead($sold, $order));
    }
}