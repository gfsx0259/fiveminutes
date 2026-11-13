<?php

declare(strict_types=1);

namespace App\Context\Lead;

use longlang\phpkafka\Producer\Producer;

final readonly class LeadQueue
{
    public function __construct(
        private Producer $producer,
        private MinedLeadMessage $message,
        private string $topic,
    ) {}

    public function publish(Lead $lead): void
    {
        $this->producer->send($this->topic, $this->message->encode($lead), $lead->getUuid());
    }
}
