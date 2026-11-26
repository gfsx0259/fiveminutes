<?php

declare(strict_types=1);

namespace App\Context\Team;

use App\Orm\Repository;

/**
 * @extends Repository<Member>
 */
class MemberRepository extends Repository
{
    public function findByEmail(string $email): ?Member
    {
        $found = $this->findOne(['email' => strtolower(trim($email))]);

        return $found instanceof Member ? $found : null;
    }

    public function findByIdentityId(string $identityId): ?Member
    {
        $found = $this->findOne(['identityId' => $identityId]);

        return $found instanceof Member ? $found : null;
    }

    public function findInAccount(string $accountId, string $id): ?Member
    {
        $found = $this->findOne(['id' => $id, 'accountId' => $accountId]);

        return $found instanceof Member ? $found : null;
    }

    /**
     * @return list<Member>
     */
    public function findInUnit(string $unitId): array
    {
        /** @var list<Member> $members */
        $members = array_values($this->select()->where(['unitId' => $unitId])->fetchAll());

        return $members;
    }

    /**
     * @return list<Member>
     */
    public function findByAccount(string $accountId): array
    {
        /** @var list<Member> $members */
        $members = array_values($this->select()->where(['accountId' => $accountId])->orderBy('createdAt')->fetchAll());

        return $members;
    }
}
