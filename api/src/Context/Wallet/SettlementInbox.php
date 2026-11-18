<?php

declare(strict_types=1);

namespace App\Context\Wallet;

use InvalidArgumentException;
use JsonException;
use longlang\phpkafka\Consumer\Consumer;

final readonly class SettlementInbox
{
    public function __construct(private Consumer $consumer) {}

    public function pull(): ?SettlementInboxItem
    {
        $message = $this->consumer->consume();
        if ($message === null || $message->getValue() === null || $message->getValue() === '') {
            return null;
        }

        try {
            $payload = LeadSettlementMessage::decode($message->getValue());
        } catch (JsonException | InvalidArgumentException $exception) {
            $this->consumer->ack($message);

            throw new InvalidArgumentException($exception->getMessage(), 0, $exception);
        }

        return new SettlementInboxItem($payload, $this->consumer, $message);
    }
}