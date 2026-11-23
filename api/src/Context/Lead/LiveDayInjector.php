<?php

declare(strict_types=1);

namespace App\Context\Lead;

use App\Clock;
use App\Context\Market\Order;
use App\Context\Market\OrganizationShelfAge;
use App\Context\Market\Preset;
use Cycle\ORM\EntityManagerInterface;
use DateTimeImmutable;
use DateTimeZone;

final readonly class LiveDayInjector
{
    /** @var list<int> */
    private const array SLOT_MINUTES = [5, 10, 15];

    private const int JITTER_SECONDS = 60;

    public function __construct(
        private LeadRepository $leads,
        private OrganizationShelfAge $shelfAge,
        private LiveDayInjectStore $store,
        private EntityManagerInterface $entityManager,
        private Clock $clock,
    ) {}

    /**
     * Schedule in-stock live leads into a new day order (delayed slots, LM-style).
     *
     * @return array{scheduled: int, leadUuids: list<string>}
     */
    public function scheduleForOrder(Order $order, Preset $preset): array
    {
        if ($order->isStock()) {
            return ['scheduled' => 0, 'leadUuids' => []];
        }
        if (!$order->hasRoom()) {
            return ['scheduled' => 0, 'leadUuids' => []];
        }

        $policy = $this->shelfAge->forOrganization($order->getOrganizationId());
        $reserved = $this->store->scheduledLeadUuids($order->getOrganizationId());
        $limit = min($order->getVolume() - $order->getSuppliedCount(), 500);
        $picked = $this->leads->findLiveInStockForPreset(
            $order->getOrganizationId(),
            $preset,
            $policy,
            $limit,
            $reserved,
        );
        if ($picked === []) {
            return ['scheduled' => 0, 'leadUuids' => []];
        }

        $now = $this->clock->now()->setTimezone(new DateTimeZone('UTC'));
        $created = $this->clock->now();
        $leadUuids = [];
        foreach ($picked as $index => $lead) {
            $dueAt = $this->dueAt($now, $index);
            $item = new LiveDayInjectItem(
                $order->getOrganizationId(),
                $order->getId(),
                $preset->getId(),
                $lead->getUuid(),
                $dueAt,
                $created,
            );
            $this->entityManager->persist($item);
            $leadUuids[] = $lead->getUuid();
        }
        $this->entityManager->run();

        return ['scheduled' => count($leadUuids), 'leadUuids' => $leadUuids];
    }

    private function dueAt(DateTimeImmutable $now, int $index): DateTimeImmutable
    {
        $bases = self::SLOT_MINUTES;
        $round = intdiv($index, count($bases));
        $slot = $bases[$index % count($bases)];
        $minutes = $slot + $round * ($bases[array_key_last($bases)] ?? 15);
        $jitter = random_int(-self::JITTER_SECONDS, self::JITTER_SECONDS);

        return $now->modify(sprintf('+%d minutes %d seconds', $minutes, $jitter));
    }
}