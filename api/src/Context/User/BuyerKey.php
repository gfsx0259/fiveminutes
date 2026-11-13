<?php

declare(strict_types=1);

namespace App\Context\User;

use App\Clock;
use Cycle\ORM\EntityManagerInterface;

final readonly class BuyerKey
{
    public function __construct(
        private ApiKeys $keys,
        private EntityManagerInterface $entityManager,
        private Clock $clock,
    ) {}

    public function issue(User $user): ApiKey
    {
        $key = $this->keys->issue();
        $user->issueApiKey($key, $this->clock);
        $this->entityManager->persist($user);
        $this->entityManager->run();

        return $key;
    }
}
