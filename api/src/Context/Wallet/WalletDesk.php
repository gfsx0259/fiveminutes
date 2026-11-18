<?php

declare(strict_types=1);

namespace App\Context\Wallet;

use App\Context\User\User;
use App\Context\User\UserRole;
use App\Value\Money;
use DomainException;
use InvalidArgumentException;

final readonly class WalletDesk
{
    private const int MOCK_STEP_CENTS = 10_000;

    public function __construct(
        private WalletLedger $ledger,
    ) {}

    public function deposit(User $user, int $amountCents): int
    {
        $this->guardWalletRole($user);
        $amount = $this->mockAmount($amountCents);
        $this->ledger->creditUser($user, $amount, TransactionKind::Adjustment, metadata: ['mock' => 'deposit']);
        $this->ledger->flush();

        return $user->getBalance()->cents();
    }

    public function withdraw(User $user, int $amountCents): int
    {
        $this->guardWalletRole($user);
        $amount = $this->mockAmount($amountCents);
        $this->ledger->debitUser($user, $amount, TransactionKind::Adjustment, metadata: ['mock' => 'withdraw']);
        $this->ledger->flush();

        return $user->getBalance()->cents();
    }

    private function guardWalletRole(User $user): void
    {
        if (!in_array($user->getRole(), [UserRole::Partner, UserRole::Buyer], true)) {
            throw new DomainException('Only partner or buyer wallets can be adjusted');
        }
    }

    private function mockAmount(int $amountCents): Money
    {
        if ($amountCents <= 0) {
            $amountCents = self::MOCK_STEP_CENTS;
        }
        if ($amountCents > 100_000_000) {
            throw new InvalidArgumentException('Amount is too large');
        }

        return new Money($amountCents);
    }
}