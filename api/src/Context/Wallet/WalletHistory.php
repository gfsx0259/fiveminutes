<?php

declare(strict_types=1);

namespace App\Context\Wallet;

use App\Context\User\User;

final readonly class WalletHistory
{
    public function __construct(
        private WalletTransactionRepository $transactions,
    ) {}

    /**
     * @return list<array<string, mixed>>
     */
    public function forUser(User $user): array
    {
        $balance = $user->getBalance()->cents();
        $rows = [];
        foreach ($this->transactions->findLatestByUser($user->getId()) as $transaction) {
            $rows[] = [
                'id' => $transaction->getId(),
                'kind' => $transaction->getKind()->value,
                'direction' => $transaction->getDirection()->value,
                'amountCents' => $transaction->getAmountCents(),
                'balanceAfterCents' => $balance,
                'leadUuid' => $transaction->getLeadUuid(),
                'orderId' => $transaction->getOrderId(),
                'createdAt' => $transaction->getCreatedAt()->format(DATE_ATOM),
            ];
            $balance -= $transaction->getSignedCents();
        }

        return $rows;
    }
}
