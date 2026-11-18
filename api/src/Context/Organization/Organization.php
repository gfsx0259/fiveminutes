<?php

declare(strict_types=1);

namespace App\Context\Organization;

use App\Clock;
use App\Value\Apex;
use App\Value\Balance;
use App\Value\Money;
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

    #[Column(type: 'smallint', name: 'markup_percent')]
    private int $markupPercent;

    #[Column(type: 'integer', typecast: 'int')]
    private int $balance;

    public function __construct(
        #[Column(type: 'uuid', primary: true)]
        private readonly string $id,
        string $name,
        string $apex,
        #[Column(type: 'datetime', name: 'created_at', typecast: 'datetime')]
        private readonly DateTimeInterface $createdAt,
        #[Column(type: 'datetime', name: 'updated_at', typecast: 'datetime')]
        private DateTimeInterface $updatedAt,
        int $markupPercent = 10,
        int $balance = 0,
    ) {
        $this->name = $this->requireName($name);
        $this->apex = (new Apex($apex))->toString();
        $this->markupPercent = $this->requireMarkupPercent($markupPercent);
        $this->balance = $balance;
        new Balance($this->balance);
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

    public function getMarkupPercent(): int
    {
        return $this->markupPercent;
    }

    public function setMarkupPercent(int $percent, Clock $clock): void
    {
        $this->markupPercent = $this->requireMarkupPercent($percent);
        $this->updatedAt = $clock->now();
    }

    public function getCreatedAt(): DateTimeInterface
    {
        return $this->createdAt;
    }

    public function getBalance(): Balance
    {
        return new Balance($this->balance);
    }

    public function credit(Money $amount, Clock $clock): void
    {
        $this->balance = $this->getBalance()->credit($amount)->cents();
        $this->updatedAt = $clock->now();
    }

    public function debit(Money $amount, Clock $clock): void
    {
        $this->balance = $this->getBalance()->debit($amount)->cents();
        $this->updatedAt = $clock->now();
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

    private function requireMarkupPercent(int $percent): int
    {
        if ($percent < 0 || $percent > 100) {
            throw new InvalidArgumentException('Markup percent must be between 0 and 100');
        }

        return $percent;
    }
}
