<?php

declare(strict_types=1);

namespace App\Context\Tree;

use App\Context\Market\CallWindow;
use App\Context\Market\Order;
use DateTimeImmutable;
use Redis;

final readonly class OrderPool
{
    private const int SOLD_TTL_SECONDS = 172800;

    public function __construct(
        private Redis $redis,
        private KeySchema $keys,
    ) {}

    public function publish(Order $order, DateTimeImmutable $now): void
    {
        $organizationId = $order->getOrganizationId();
        $pool = $this->keys->pool($organizationId, $order->getPresetId());
        $data = $this->keys->data($organizationId, $order->getId());
        if (!$order->hasRoom()) {
            $this->redis->del($data);
            $this->redis->sRem($pool, $order->getId());
            if ((int) $this->redis->sCard($pool) === 0) {
                $this->redis->del($pool);
            }

            return;
        }

        $window = new CallWindow($order->getOpensAt(), $order->getClosesAt());
        $serviceOn = $order->getServiceOn();
        $this->redis->hMSet($data, [
            'partner_id' => $order->getPartnerId(),
            'preset_id' => $order->getPresetId(),
            'rate' => (string) $order->getPriceCents(),
            'availability_utc' => $window->availabilityUtc($order->getTimezone(), $order->getServiceOn()),
            'capacity' => (string) $order->getVolume(),
            'geo' => $order->getGeo(),
            'date' => $serviceOn === null ? '' : $serviceOn->format('Y-m-d'),
            'stock' => $order->isStock() ? '1' : '0',
        ]);
        $this->redis->sAdd($pool, $order->getId());
        $this->redis->setex(
            $this->keys->sold($organizationId, $order->getId(), $this->localDay($now)),
            self::SOLD_TTL_SECONDS,
            (string) $order->getSuppliedCount(),
        );
    }

    public function release(string $organizationId, string $orderId, int $localDay): void
    {
        $key = $this->keys->sold($organizationId, $orderId, $localDay);
        $left = $this->redis->decr($key);
        if ($left < 0) {
            $this->redis->set($key, '0');
        }
    }

    public function localDay(DateTimeImmutable $at): int
    {
        return intdiv($at->getTimestamp(), 86400);
    }
}
