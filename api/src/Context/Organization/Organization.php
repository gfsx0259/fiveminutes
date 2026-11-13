<?php

declare(strict_types=1);

namespace App\Context\Organization;

use App\Clock;
use App\Value\Apex;
use Cycle\Annotated\Annotation\Column;
use Cycle\Annotated\Annotation\Entity;
use DateTimeInterface;
use InvalidArgumentException;

#[Entity(repository: OrganizationRepository::class, table: 'organizations')]
class Organization
{
    #[Column(type: 'string')]
    private string $name;

    #[Column(type: 'string')]
    private readonly string $apex;

    public function __construct(
        #[Column(type: 'uuid', primary: true)]
        private readonly string $id,
        string $name,
        string $apex,
        #[Column(type: 'datetime', name: 'created_at', typecast: 'datetime')]
        private readonly DateTimeInterface $createdAt,
        #[Column(type: 'datetime', name: 'updated_at', typecast: 'datetime')]
        private DateTimeInterface $updatedAt,
    ) {
        $this->name = $this->requireName($name);
        $this->apex = (new Apex($apex))->toString();
    }

    public function getId(): string
    {
        return $this->id;
    }

    public function getName(): string
    {
        return $this->name;
    }

    public function getApex(): string
    {
        return $this->apex;
    }

    public function getCreatedAt(): DateTimeInterface
    {
        return $this->createdAt;
    }

    public function rename(string $name, Clock $clock): void
    {
        $this->name = $this->requireName($name);
        $this->updatedAt = $clock->now();
    }

    private function requireName(string $name): string
    {
        $name = trim($name);
        if ($name === '') {
            throw new InvalidArgumentException('Name is empty');
        }

        return $name;
    }
}
