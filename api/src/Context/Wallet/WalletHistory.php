<?php

declare(strict_types=1);

namespace App\Context\Wallet;

use App\Context\User\User;

final readonly class WalletHistory
{
    private const int LIMIT = 200;

    public function __construct(
        private WalletTransactionRepository $transactions,
    ) {}

    /**
     * @return list<array<string, mixed>>
     */
    public function forUser(User $user): array
    {
        return array_values($this->transactions->findLatestByUser($user->getId(), self::LIMIT)->map(
            fn (WalletTransaction $transaction): array => [
                'id' => $transaction->getId(),
                'kind' => $transaction->getKind()->value,
                'direction' => $transaction->getDirection()->value,
                'amountCents' => $transaction->getAmountCents(),
                'leadUuid' => $transaction->getLeadUuid(),
                'orderId' => $transaction->getOrderId(),
                'createdAt' => $transaction->getCreatedAt()->format(DATE_ATOM),
            ],
        )->toArray());
    }
}
