<?php

declare(strict_types=1);

namespace App\Context\Lead;

use App\Clock;
use App\Context\Market\Acceptance;
use App\Context\Market\Book;
use Cycle\ORM\EntityManagerInterface;
use Cycle\ORM\ORMInterface;
use DateTimeImmutable;
use DateTimeInterface;
use DateTimeZone;
use PDOException;
use Throwable;

final readonly class Sale
{
    public function __construct(
        private Book $book,
        private PlacementRepository $placements,
        private LeadRepository $leads,
        private EntityManagerInterface $entityManager,
        private ORMInterface $orm,
        private Clock $clock,
    ) {}

    public function place(Lead $lead): Lead
    {
        $this->orm->getHeap()->clean();
        $existing = $this->placements->findByLead($lead->getUuid());
        if ($existing !== null) {
            $stored = $this->stored($lead, $existing);
            $this->leads->add($stored);

            return $stored;
        }

        $at = $this->clock->now()->setTimezone(new DateTimeZone('UTC'));
        $acceptance = $this->book->choose($lead->getOrganizationId(), $lead->getBuyerId(), $lead->getCountry(), $at);
        $stored = $this->decide($lead, $acceptance, $at);
        $this->entityManager->persist(new Placement(
            $lead->getUuid(),
            $stored->getAcceptanceId(),
            $stored->getSellerId(),
            $stored->getPriceCents(),
            $stored->getSoldAt(),
            $at,
        ));
        if ($acceptance !== null) {
            $this->entityManager->persist($acceptance);
        }

        try {
            $this->entityManager->run();
        } catch (Throwable $exception) {
            $this->orm->getHeap()->clean();
            if (!$this->duplicate($exception)) {
                throw $exception;
            }
            $existing = $this->placements->findByLead($lead->getUuid());
            if ($existing === null) {
                throw $exception;
            }
            $stored = $this->stored($lead, $existing);
        }

        $this->leads->add($stored);

        return $stored;
    }

    private function decide(Lead $lead, ?Acceptance $acceptance, DateTimeImmutable $at): Lead
    {
        if ($acceptance === null) {
            return $lead->hold($at);
        }

        $acceptance->reserve();

        return $lead->sell(
            $acceptance->getPartnerId(),
            $acceptance->getId(),
            $acceptance->getBuyerPriceCents(),
            $at,
        );
    }

    private function stored(Lead $lead, Placement $placement): Lead
    {
        $soldAt = $placement->getSoldAt();
        if ($soldAt === null || $placement->getSellerId() === null || $placement->getAcceptanceId() === null) {
            return $lead->hold($this->instant($placement->getCreatedAt()));
        }

        return $lead->sell(
            $placement->getSellerId(),
            $placement->getAcceptanceId(),
            $placement->getPriceCents(),
            $this->instant($soldAt),
        );
    }

    private function instant(DateTimeInterface $at): DateTimeImmutable
    {
        return DateTimeImmutable::createFromInterface($at)->setTimezone(new DateTimeZone('UTC'));
    }

    private function duplicate(Throwable $exception): bool
    {
        while ($exception !== null) {
            if ($exception instanceof PDOException && ($exception->errorInfo[0] ?? null) === '23505') {
                return true;
            }
            if (str_contains($exception->getMessage(), '23505')) {
                return true;
            }
            $exception = $exception->getPrevious();
        }

        return false;
    }
}
