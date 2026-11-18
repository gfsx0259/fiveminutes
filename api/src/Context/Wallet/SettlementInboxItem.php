<?php

declare(strict_types=1);

namespace App\Context\Wallet;

use longlang\phpkafka\Consumer\ConsumeMessage;
use longlang\phpkafka\Consumer\Consumer;

final readonly class SettlementInboxItem
{
    public function __construct(
        public SettlementPayload $payload,
        private Consumer $consumer,
        private ConsumeMessage $message,
    ) {}

    public function ack(): void
    {
        $this->consumer->ack($this->message);
    }
}