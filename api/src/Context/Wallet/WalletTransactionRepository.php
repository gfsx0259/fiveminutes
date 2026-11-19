<?php

declare(strict_types=1);

namespace App\Context\Wallet;

use App\Orm\Repository;
use Doctrine\Common\Collections\Collection;

/**
 * @extends Repository<WalletTransaction>
 */
class WalletTransactionRepository extends Repository
{
    /**
     * @return Collection<int, WalletTransaction>
     */
    public function findByUser(string $userId): Collection
    {
        return $this->collection(['userId' => $userId]);
    }

    /**
     * @return Collection<int, WalletTransaction>
     */
    public function findLatestByUser(string $userId, int $limit): Collection
    {
        return $this->ordered(['userId' => $userId], $limit);
    }
}
