<?php

declare(strict_types=1);

namespace App\Context\Wallet;

use App\Clock;
use App\Context\Organization\Organization;
use App\Context\User\User;
use App\Value\Money;
use Cycle\ORM\EntityManagerInterface;
use Ramsey\Uuid\UuidFactoryInterface;

final readonly class WalletLedger
{
    public function __construct(
        private UuidFactoryInterface $uuids,
        private Clock $clock,
        private EntityManagerInterface $entityManager,
    ) {}

    public function creditUser(
        User $user,
        Money $amount,
        TransactionKind $kind,
        ?string $leadUuid = null,
        ?string $orderId = null,
        array $metadata = [],
    ): void {
        $user->credit($amount, $this->clock);
        $this->persist(new WalletTransaction(
            $this->uuids->uuid4()->toString(),
            $user->getOrganizationId(),
            $user->getId(),
            Direction::Credit,
            $amount->cents(),
            $kind,
            $leadUuid,
            $orderId,
            $this->clock->now(),
            $metadata,
        ));
        $this->entityManager->persist($user);
    }

    public function debitUser(
        User $user,
        Money $amount,
        TransactionKind $kind,
        ?string $leadUuid = null,
        ?string $orderId = null,
        array $metadata = [],
    ): void {
        $user->debit($amount, $this->clock);
        $this->persist(new WalletTransaction(
            $this->uuids->uuid4()->toString(),
            $user->getOrganizationId(),
            $user->getId(),
            Direction::Debit,
            $amount->cents(),
            $kind,
            $leadUuid,
            $orderId,
            $this->clock->now(),
            $metadata,
        ));
        $this->entityManager->persist($user);
    }

    public function creditOrganization(
        Organization $organization,
        Money $amount,
        TransactionKind $kind,
        ?string $leadUuid = null,
        ?string $orderId = null,
        array $metadata = [],
    ): void {
        $organization->credit($amount, $this->clock);
        $this->persist(new WalletTransaction(
            $this->uuids->uuid4()->toString(),
            $organization->getId(),
            null,
            Direction::Credit,
            $amount->cents(),
            $kind,
            $leadUuid,
            $orderId,
            $this->clock->now(),
            $metadata,
        ));
        $this->entityManager->persist($organization);
    }

    private function persist(WalletTransaction $transaction): void
    {
        $this->entityManager->persist($transaction);
    }

    public function flush(): void
    {
        $this->entityManager->run();
    }
}