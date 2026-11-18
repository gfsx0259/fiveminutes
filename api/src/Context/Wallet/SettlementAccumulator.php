<?php

declare(strict_types=1);

namespace App\Context\Wallet;

use Psr\Log\LoggerInterface;
use Throwable;

final class SettlementAccumulator
{
    private const int FLUSH_COUNT = 200;
    private const int FLUSH_INTERVAL_SECONDS = 30;

    /** @var list<SettlementInboxItem> */
    private array $buffer = [];

    private ?int $bufferedSince = null;

    public function __construct(
        private readonly SettlementApply $apply,
        private readonly LoggerInterface $logger,
    ) {}

    public function add(SettlementInboxItem $item): void
    {
        $this->buffer[] = $item;
        $this->bufferedSince ??= time();
        if (count($this->buffer) >= self::FLUSH_COUNT) {
            $this->flush();
        }
    }

    public function onIdle(): void
    {
        if ($this->bufferedSince !== null && time() - $this->bufferedSince >= self::FLUSH_INTERVAL_SECONDS) {
            $this->flush();
        }
    }

    public function onShutdown(): void
    {
        $this->flush();
    }

    private function flush(): void
    {
        if ($this->buffer === []) {
            return;
        }

        $failedAt = null;
        foreach ($this->buffer as $index => $item) {
            try {
                $this->apply->apply($item->payload);
                $item->ack();
            } catch (Throwable $exception) {
                $this->logger->error('Settlement apply failed', [
                    'leadUuid' => $item->payload->leadUuid,
                    'error' => $exception->getMessage(),
                ]);
                $failedAt = $index;
                break;
            }
        }

        $this->buffer = $failedAt === null
            ? []
            : array_values(array_slice($this->buffer, $failedAt));

        $this->bufferedSince = $this->buffer === [] ? null : time();
    }
}