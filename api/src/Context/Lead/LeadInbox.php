<?php

declare(strict_types=1);

namespace App\Context\Lead;

use InvalidArgumentException;
use JsonException;
use longlang\phpkafka\Consumer\ConsumeMessage;
use longlang\phpkafka\Consumer\Consumer;

final class LeadInbox
{
    private ?ConsumeMessage $pending = null;

    public function __construct(
        private readonly Consumer $consumer,
        private readonly MinedLeadMessage $message,
    ) {}

    public function pull(): ?Lead
    {
        $message = $this->consumer->consume();
        if ($message === null || $message->getValue() === null || $message->getValue() === '') {
            return null;
        }

        try {
            $lead = $this->message->decode($message->getValue());
        } catch (JsonException | InvalidArgumentException $exception) {
            $this->consumer->ack($message);
            throw new InvalidArgumentException($exception->getMessage(), 0, $exception);
        }

        $this->pending = $message;

        return $lead;
    }

    public function ack(): void
    {
        if ($this->pending === null) {
            return;
        }

        $this->consumer->ack($this->pending);
        $this->pending = null;
    }
}
