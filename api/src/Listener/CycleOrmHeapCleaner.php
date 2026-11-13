<?php

declare(strict_types=1);

namespace App\Listener;

use Cycle\ORM\ORMInterface;
use Yiisoft\Yii\Http\Event\AfterRequest;

final class CycleOrmHeapCleaner
{
    public function __construct(
        private readonly ORMInterface $orm,
    ) {}

    public function __invoke(AfterRequest $event): void
    {
        $this->orm->getHeap()->clean();
    }
}
