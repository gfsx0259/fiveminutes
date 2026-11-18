<?php

declare(strict_types=1);

namespace App\Context\Wallet;

use longlang\phpkafka\Producer\Producer;

final readonly class SettlementQueue
{
    public function __construct(
        private Producer $producer,
        private string $topic,
    ) {}

    public function publish(LeadSettlementMessage $message): void
    {
        $this->producer->send($this->topic, $message->encode(), $message->leadUuid);
    }
}