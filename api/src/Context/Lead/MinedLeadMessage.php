<?php

declare(strict_types=1);

namespace App\Context\Lead;

use App\Value\MetaMap;
use DateTimeImmutable;
use InvalidArgumentException;

final readonly class MinedLeadMessage
{
    public function encode(Lead $lead): string
    {
        return json_encode([
            'uuid' => $lead->getUuid(),
            'organization_id' => $lead->getOrganizationId(),
            'buyer_id' => $lead->getBuyerId(),
            'email' => $lead->getEmail(),
            'phone' => $lead->getPhone(),
            'first_name' => $lead->getFirstName(),
            'last_name' => $lead->getLastName(),
            'country' => $lead->getCountry(),
            'traffic_source' => $lead->getTrafficSource(),
            'age' => $lead->getAge(),
            'gender' => $lead->getGender(),
            'ip' => $lead->getIp(),
            'aff_sub' => $lead->getAffSub(),
            'meta' => $lead->getMeta(),
            'uniq_id' => $lead->getUniqId(),
            'created_at' => $lead->getCreatedAt()->format(DATE_ATOM),
        ], JSON_THROW_ON_ERROR);
    }

    public function decode(string $payload): Lead
    {
        $body = json_decode($payload, true, 512, JSON_THROW_ON_ERROR);
        if (!is_array($body)) {
            throw new InvalidArgumentException('Lead message is not an object');
        }

        $createdAt = new DateTimeImmutable($this->text($body, 'created_at'));

        return (new Lead(
            $this->text($body, 'uuid'),
            $this->text($body, 'organization_id'),
            $this->text($body, 'buyer_id'),
            null,
            null,
            $this->text($body, 'email'),
            $this->text($body, 'phone'),
            $this->text($body, 'first_name'),
            $this->text($body, 'last_name'),
            $this->text($body, 'country'),
            $this->optional($body, 'traffic_source'),
            $this->optional($body, 'age'),
            $this->optional($body, 'gender'),
            $this->text($body, 'ip'),
            $this->text($body, 'aff_sub'),
            $this->text($body, 'uniq_id'),
            LeadStatus::New,
            0,
            $createdAt,
            $createdAt,
        ))->withMeta(MetaMap::from($body['meta'] ?? [])->pairs());
    }

    /**
     * @param array<mixed, mixed> $body
     */
    private function text(array $body, string $field): string
    {
        $value = $body[$field] ?? null;
        if (!is_string($value) || trim($value) === '') {
            throw new InvalidArgumentException('Lead message field ' . $field . ' is empty');
        }

        return $value;
    }

    /**
     * @param array<mixed, mixed> $body
     */
    private function optional(array $body, string $field): string
    {
        $value = $body[$field] ?? '';

        return is_string($value) ? trim($value) : '';
    }
}
