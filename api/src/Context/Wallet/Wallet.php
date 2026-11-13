<?php

declare(strict_types=1);

namespace App\Context\Wallet;

use App\Clock;
use App\Context\User\User;
use App\UuidGenerator;
use App\Value\Money;

final class Wallet
{
    public function __construct(
        private UuidGenerator $uuids,
        private Clock $clock,
    ) {}

    public function credit(User $user, Money $amount): WalletTransaction
    {
        $user->credit($amount, $this->clock);

        return $this->entry($user, Direction::Credit, $amount);
    }

    public function debit(User $user, Money $amount): WalletTransaction
    {
        $user->debit($amount, $this->clock);

        return $this->entry($user, Direction::Debit, $amount);
    }

    private function entry(User $user, Direction $direction, Money $amount): WalletTransaction
    {
        return new WalletTransaction(
            $this->uuids->v4(),
            $user->getOrganizationId(),
            $user->getId(),
            $direction,
            $amount->cents(),
            $this->clock->now(),
        );
    }
}
