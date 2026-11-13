<?php

declare(strict_types=1);

namespace App\Context\Market;

use Cycle\Annotated\Annotation\Column;
use DateTimeInterface;

trait HasListingStatus
{
    #[Column(type: 'string')]
    private string $status;

    #[Column(type: 'datetime', name: 'updated_at', typecast: 'datetime')]
    private DateTimeInterface $updatedAt;

    public function getStatus(): ListingStatus
    {
        return ListingStatus::from($this->status);
    }

    public function getUpdatedAt(): DateTimeInterface
    {
        return $this->updatedAt;
    }

    public function markTaken(ListingTransition $transition): void
    {
        $this->applyStatus(ListingStatus::Taken, $transition);
    }

    protected function closeAsAuthor(string $actorId, string $authorId, ListingTransition $transition): void
    {
        if ($actorId !== $authorId) {
            throw new \DomainException('Only the author can close the listing');
        }

        $this->applyStatus(ListingStatus::Closed, $transition);
    }

    private function applyStatus(ListingStatus $next, ListingTransition $transition): void
    {
        $change = $transition->change($this->getStatus(), $next);
        $this->status = $change->getStatus()->value;
        $this->updatedAt = $change->getAt();
    }
}
