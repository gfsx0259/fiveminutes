<?php

declare(strict_types=1);

namespace App\Context\Wallet;

use App\Context\Lead\Lead;
use App\Context\Market\Order;
use App\Context\Market\PaymentModel;
use InvalidArgumentException;
use JsonException;

final readonly class LeadSettlementMessage
{
    public const string ENVELOPE_EVENT = 'lead.settlement';

    public function __construct(
        public string $leadUuid,
        public string $orderId,
        public SettlementEvent $kind,
        public int $catalogCents,
        public string $partnerId,
        public string $buyerId,
        public string $organizationId,
    ) {}

    public static function fromSoldLead(Lead $lead, Order $order): self
    {
        $sellerId = $lead->getSellerId();
        $orderId = $lead->getOrderId();
        if ($sellerId === null || $orderId === null) {
            throw new InvalidArgumentException('Matched lead is missing sale metadata');
        }
        $kind = PaymentModel::from($order->getPaymentModel()) === PaymentModel::Cpl
            ? SettlementEvent::CplDelivered
            : SettlementEvent::CpaSold;

        return new self(
            $lead->getUuid(),
            $orderId,
            $kind,
            $lead->getPriceCents(),
            $sellerId,
            $lead->getBuyerId(),
            $lead->getOrganizationId(),
        );
    }

    /**
     * @param array<string, mixed> $body
     */
    public static function decode(string $body): SettlementPayload
    {
        try {
            $decoded = json_decode($body, true, 512, JSON_THROW_ON_ERROR);
        } catch (JsonException $exception) {
            throw new InvalidArgumentException('Settlement message is not JSON', 0, $exception);
        }
        if (!is_array($decoded)) {
            throw new InvalidArgumentException('Settlement message must be an object');
        }
        if (($decoded['event'] ?? null) !== self::ENVELOPE_EVENT) {
            throw new InvalidArgumentException('Settlement envelope event is unknown');
        }
        $payload = $decoded['payload'] ?? null;
        if (!is_array($payload)) {
            throw new InvalidArgumentException('Settlement payload is missing');
        }

        return new SettlementPayload(
            self::requireString($payload, 'leadUuid'),
            self::requireString($payload, 'orderId'),
            SettlementEvent::from(self::requireString($payload, 'kind')),
            self::requireInt($payload, 'catalogCents'),
            self::requireString($payload, 'partnerId'),
            self::requireString($payload, 'buyerId'),
            self::requireString($payload, 'organizationId'),
        );
    }

    public function encode(): string
    {
        try {
            return json_encode([
                'event' => self::ENVELOPE_EVENT,
                'payload' => [
                    'leadUuid' => $this->leadUuid,
                    'orderId' => $this->orderId,
                    'kind' => $this->kind->value,
                    'catalogCents' => $this->catalogCents,
                    'partnerId' => $this->partnerId,
                    'buyerId' => $this->buyerId,
                    'organizationId' => $this->organizationId,
                ],
            ], JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE);
        } catch (JsonException $exception) {
            throw new JsonException('Settlement message is not valid JSON', 0, $exception);
        }
    }

    /**
     * @param array<string, mixed> $payload
     */
    private static function requireString(array $payload, string $key): string
    {
        $value = trim((string) ($payload[$key] ?? ''));
        if ($value === '') {
            throw new InvalidArgumentException($key . ' is empty');
        }

        return $value;
    }

    /**
     * @param array<string, mixed> $payload
     */
    private static function requireInt(array $payload, string $key): int
    {
        if (!isset($payload[$key]) || !is_numeric($payload[$key])) {
            throw new InvalidArgumentException($key . ' is invalid');
        }
        $value = (int) $payload[$key];
        if ($value < 0) {
            throw new InvalidArgumentException($key . ' is negative');
        }

        return $value;
    }
}