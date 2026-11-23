<?php

declare(strict_types=1);

namespace App\Context\Lead;

use App\Clock;
use DateTimeImmutable;
use DateTimeInterface;
use InvalidArgumentException;

final readonly class Lead
{
    private string $uuid;
    private string $organizationId;
    private string $buyerId;
    private ?string $sellerId;
    private ?string $orderId;
    private string $email;
    private string $phone;
    private string $firstName;
    private string $lastName;
    private string $country;
    private string $trafficSource;
    private string $age;
    private string $gender;
    private string $ip;
    private string $affSub;
    private string $uniqId;
    private string $status;
    private bool $isDelivered;
    private bool $deliveryError;
    private string $clientStatus;
    private bool $clientDeposit;

    public function __construct(
        string $uuid,
        string $organizationId,
        string $buyerId,
        ?string $sellerId,
        ?string $orderId,
        string $email,
        string $phone,
        string $firstName,
        string $lastName,
        string $country,
        string $trafficSource,
        string $age,
        string $gender,
        string $ip,
        string $affSub,
        string $uniqId,
        LeadStatus $status,
        private int $priceCents,
        private DateTimeInterface $createdAt,
        private DateTimeInterface $updatedAt,
        private ?DateTimeInterface $soldAt = null,
        bool $isDelivered = false,
        bool $deliveryError = false,
        string $clientStatus = '',
        bool $clientDeposit = false,
    ) {
        $this->uuid = $this->requireId($uuid, 'Lead uuid is empty');
        $this->organizationId = $this->requireId($organizationId, 'Organization id is empty');
        $this->buyerId = $this->requireId($buyerId, 'Buyer id is empty');
        $this->sellerId = $this->optionalId($sellerId);
        $this->orderId = $this->optionalId($orderId);
        $this->email = trim($email);
        $this->phone = trim($phone);
        $this->firstName = trim($firstName);
        $this->lastName = trim($lastName);
        $this->country = trim($country);
        $this->trafficSource = trim($trafficSource);
        $this->age = trim($age);
        $this->gender = trim($gender);
        $this->ip = trim($ip);
        $this->affSub = trim($affSub);
        $uniqId = trim($uniqId);
        if ($uniqId === '') {
            throw new InvalidArgumentException('Uniq id is empty');
        }
        $this->uniqId = $uniqId;
        $this->status = $status->value;
        if ($this->priceCents < 0) {
            throw new InvalidArgumentException('Price must be zero or positive');
        }
        $this->isDelivered = $isDelivered;
        $this->deliveryError = $deliveryError;
        $this->clientStatus = trim($clientStatus);
        $this->clientDeposit = $clientDeposit;
    }

    public function sell(string $sellerId, string $orderId, int $priceCents, DateTimeImmutable $at): self
    {
        return $this->copy($sellerId, $orderId, LeadStatus::Sold, $priceCents, $at, $at);
    }

    public function hold(DateTimeImmutable $at): self
    {
        return $this->copy(null, null, LeadStatus::InStock, 0, null, $at);
    }

    public function getUuid(): string
    {
        return $this->uuid;
    }

    public function getOrganizationId(): string
    {
        return $this->organizationId;
    }

    public function getBuyerId(): string
    {
        return $this->buyerId;
    }

    public function getSellerId(): ?string
    {
        return $this->sellerId;
    }

    public function getOrderId(): ?string
    {
        return $this->orderId;
    }

    public function getEmail(): string
    {
        return $this->email;
    }

    public function getPhone(): string
    {
        return $this->phone;
    }

    public function getFirstName(): string
    {
        return $this->firstName;
    }

    public function getLastName(): string
    {
        return $this->lastName;
    }

    public function getCountry(): string
    {
        return $this->country;
    }

    public function getTrafficSource(): string
    {
        return $this->trafficSource;
    }

    public function getAge(): string
    {
        return $this->age;
    }

    public function getGender(): string
    {
        return $this->gender;
    }

    public function getIp(): string
    {
        return $this->ip;
    }

    public function getAffSub(): string
    {
        return $this->affSub;
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

    public function getSoldAt(): ?DateTimeInterface
    {
        return $this->soldAt;
    }

    public function isDelivered(): bool
    {
        return $this->isDelivered;
    }

    public function hasDeliveryError(): bool
    {
        return $this->deliveryError;
    }

    public function getClientStatus(): string
    {
        return $this->clientStatus;
    }

    public function hasClientDeposit(): bool
    {
        return $this->clientDeposit;
    }

    public function withStatus(LeadStatus $status, Clock $clock): self
    {
        return new self(
            $this->uuid,
            $this->organizationId,
            $this->buyerId,
            $this->sellerId,
            $this->orderId,
            $this->email,
            $this->phone,
            $this->firstName,
            $this->lastName,
            $this->country,
            $this->trafficSource,
            $this->age,
            $this->gender,
            $this->ip,
            $this->affSub,
            $this->uniqId,
            $status,
            $this->priceCents,
            $this->createdAt,
            $clock->now(),
            $this->soldAt,
            $this->isDelivered,
            $this->deliveryError,
            $this->clientStatus,
            $this->clientDeposit,
        );
    }

    public function withCreatedAt(DateTimeInterface $createdAt, DateTimeInterface $updatedAt): self
    {
        return new self(
            $this->uuid,
            $this->organizationId,
            $this->buyerId,
            $this->sellerId,
            $this->orderId,
            $this->email,
            $this->phone,
            $this->firstName,
            $this->lastName,
            $this->country,
            $this->trafficSource,
            $this->age,
            $this->gender,
            $this->ip,
            $this->affSub,
            $this->uniqId,
            $this->getStatus(),
            $this->priceCents,
            $createdAt,
            $updatedAt,
            $this->soldAt,
            $this->isDelivered,
            $this->deliveryError,
            $this->clientStatus,
            $this->clientDeposit,
        );
    }

    public function withPartnerDelivery(
        bool $isDelivered,
        bool $deliveryError,
        string $clientStatus,
        bool $clientDeposit,
        Clock $clock,
    ): self {
        return new self(
            $this->uuid,
            $this->organizationId,
            $this->buyerId,
            $this->sellerId,
            $this->orderId,
            $this->email,
            $this->phone,
            $this->firstName,
            $this->lastName,
            $this->country,
            $this->trafficSource,
            $this->age,
            $this->gender,
            $this->ip,
            $this->affSub,
            $this->uniqId,
            $this->getStatus(),
            $this->priceCents,
            $this->createdAt,
            $clock->now(),
            $this->soldAt,
            $isDelivered,
            $deliveryError,
            $clientStatus,
            $clientDeposit,
        );
    }

    private function copy(
        ?string $sellerId,
        ?string $orderId,
        LeadStatus $status,
        int $priceCents,
        ?DateTimeInterface $soldAt,
        DateTimeInterface $updatedAt,
    ): self {
        return new self(
            $this->uuid,
            $this->organizationId,
            $this->buyerId,
            $sellerId,
            $orderId,
            $this->email,
            $this->phone,
            $this->firstName,
            $this->lastName,
            $this->country,
            $this->trafficSource,
            $this->age,
            $this->gender,
            $this->ip,
            $this->affSub,
            $this->uniqId,
            $status,
            $priceCents,
            $this->createdAt,
            $updatedAt,
            $soldAt,
            $this->isDelivered,
            $this->deliveryError,
            $this->clientStatus,
            $this->clientDeposit,
        );
    }

    private function requireId(string $value, string $message): string
    {
        $value = trim($value);
        if ($value === '') {
            throw new InvalidArgumentException($message);
        }

        return $value;
    }

    private function optionalId(?string $value): ?string
    {
        $value = trim((string) $value);

        return $value === '' ? null : $value;
    }
}
