<?php

declare(strict_types=1);

namespace App\Context\Tree;

final readonly class KeySchema
{
    public function pool(string $organizationId, string $presetId): string
    {
        return $this->prefix($organizationId) . 'preset:' . $presetId . ':orders';
    }

    public function data(string $organizationId, string $orderId): string
    {
        return $this->prefix($organizationId) . 'order:' . $orderId . ':data';
    }

    public function sold(string $organizationId, string $orderId, int $localDay): string
    {
        return $this->prefix($organizationId) . 'order:' . $orderId . ':sold:' . $localDay;
    }

    public function prefix(string $organizationId): string
    {
        return $organizationId . ':';
    }
}
