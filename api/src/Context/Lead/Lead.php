<?php

declare(strict_types=1);

namespace App\Context\Lead;

use App\Clock;
use App\Value\Money;
use DateTimeInterface;

final class Lead
{
    private string $uuid;
    private string $organizationId;
    private string $acceptanceId;
    private string $email;
    private string $phone;
    private string $uniqId;
    private string $status;
    private int $priceCents;
    private DateTimeInterface $createdAt;
    private DateTimeInterface $updatedAt;

    public function __construct(
        string $uuid,
        string $organizationId,
        string $acceptanceId,
        string $email,
        string $phone,
        string $uniqId,
        LeadStatus $status,
        int $priceCents,
        DateTimeInterface $createdAt,
        DateTimeInterface $updatedAt,
    ) {
        $this->uuid = $this->requireId($uuid, 'Lead uuid is empty');
        $this->organizationId = $this->requireId($organizationId, 'Organization id is empty');
        $this->acceptanceId = $this->requireId($acceptanceId, 'Acceptance id is empty');
        $this->email = trim($email);
        $this->phone = trim($phone);
        $uniqId = trim($uniqId);
        if ($uniqId === '') {
            throw new \InvalidArgumentException('Uniq id is empty');
        }
        $this->uniqId = $uniqId;
        $this->status = $status->value;
        $this->priceCents = (new Money($priceCents))->cents();
        $this->createdAt = $createdAt;
        $this->updatedAt = $updatedAt;
    }

    public function getUuid(): string
    {
        return $this->uuid;
    }

    public function getOrganizationId(): string
    {
        return $this->organizationId;
    }

    public function getAcceptanceId(): string
    {
        return $this->acceptanceId;
    }

    public function getEmail(): string
    {
        return $this->email;
    }

    public function getPhone(): string
    {
        return $this->phone;
    }

    public function getUniqId(): string
    {
        return $this->uniqId;
    }

    public function getStatus(): LeadStatus
    {
        return LeadStatus::from($this->status);
    }

    public function getPriceCents(): int
    {
        return $this->priceCents;
    }

    public function getCreatedAt(): DateTimeInterface
    {
        return $this->createdAt;
    }

    public function getUpdatedAt(): DateTimeInterface
    {
        return $this->updatedAt;
    }

    public function withStatus(LeadStatus $status, Clock $clock): self
    {
        return new self(
            $this->uuid,
            $this->organizationId,
            $this->acceptanceId,
            $this->email,
            $this->phone,
            $this->uniqId,
            $status,
            $this->priceCents,
            $this->createdAt,
            $clock->now(),
        );
    }

    private function requireId(string $value, string $message): string
    {
        $value = trim($value);
        if ($value === '') {
            throw new \InvalidArgumentException($message);
        }

        return $value;
    }
}
