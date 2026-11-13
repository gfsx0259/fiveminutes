<?php

declare(strict_types=1);

namespace App\Orm;

use Cycle\ORM\Select\Repository as SelectRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;

/**
 * @template TEntity of object
 * @extends SelectRepository<TEntity>
 */
abstract class Repository extends SelectRepository
{
    /**
     * @param array<string, mixed> $scope
     * @return Collection<int, TEntity>
     */
    protected function collection(array $scope = []): Collection
    {
        /** @var list<TEntity> $entities */
        $entities = array_values($this->select()->where($scope)->fetchAll());

        return new ArrayCollection($entities);
    }

    /**
     * @param array<string, mixed> $scope
     * @return Collection<int, TEntity>
     */
    protected function ordered(array $scope): Collection
    {
        /** @var list<TEntity> $entities */
        $entities = array_values($this->select()->where($scope)->orderBy('createdAt', 'DESC')->fetchAll());

        return new ArrayCollection($entities);
    }
}
