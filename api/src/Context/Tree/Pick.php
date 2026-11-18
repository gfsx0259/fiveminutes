<?php

declare(strict_types=1);

namespace App\Context\Tree;

final readonly class Pick
{
    public function __construct(
        public string $orderId,
        public string $partnerId,
        public int $priceCents,
        public int $localDay,
        public bool $stock,
    ) {}
}
