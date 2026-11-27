<?php

declare(strict_types=1);

namespace App\Context\Market;

final readonly class RotationPicture
{
    /**
     * @param iterable<Preset> $presets
     * @param iterable<Order> $orders
     * @param array<string, string> $emails
     * @return list<array<string, mixed>>
     */
    public function nodes(RotationAxes $axes, iterable $presets, iterable $orders, array $emails): array
    {
        $ordersByPreset = [];
        foreach ($orders as $order) {
            if (!$order instanceof Order || $order->getStatus() !== OrderStatus::InProgress) {
                continue;
            }
            $ordersByPreset[$order->getPresetId()][] = $order;
        }

        $rows = [];
        foreach ($presets as $preset) {
            if ($preset instanceof Preset) {
                $rows[] = $preset;
            }
        }

        return $this->group($axes->axes, $rows, $ordersByPreset, $emails);
    }

    /**
     * @param list<RotationAxis> $axes
     * @param list<Preset> $presets
     * @param array<string, list<Order>> $ordersByPreset
     * @param array<string, string> $emails
     * @return list<array<string, mixed>>
     */
    private function group(array $axes, array $presets, array $ordersByPreset, array $emails): array
    {
        $head = $axes[0];
        $rest = array_slice($axes, 1);
        $buckets = [];
        foreach ($presets as $preset) {
            $buckets[$this->value($preset, $head)][] = $preset;
        }
        uksort($buckets, static function (int|string $left, int|string $right) use ($head): int {
            if ($head === RotationAxis::PaymentModel) {
                return PresetMatch::paymentOrder((string) $left) <=> PresetMatch::paymentOrder((string) $right);
            }
            $leftStar = PresetMatch::star((string) $left);
            $rightStar = PresetMatch::star((string) $right);
            if ($leftStar !== $rightStar) {
                return $leftStar ? 1 : -1;
            }

            return strcasecmp((string) $left, (string) $right);
        });

        $nodes = [];
        foreach ($buckets as $value => $group) {
            $node = [
                'axis' => $head->value,
                'value' => (string) $value,
            ];
            if ($rest === []) {
                $node['presets'] = $this->leaves($group, $ordersByPreset, $emails);
            } else {
                $node['children'] = $this->group($rest, $group, $ordersByPreset, $emails);
            }
            $nodes[] = $node;
        }

        return $nodes;
    }

    /**
     * @param list<Preset> $presets
     * @param array<string, list<Order>> $ordersByPreset
     * @param array<string, string> $emails
     * @return list<array<string, mixed>>
     */
    private function leaves(array $presets, array $ordersByPreset, array $emails): array
    {
        usort($presets, static function (Preset $left, Preset $right): int {
            return [
                $left->getTrafficSource(),
                $left->getAge(),
                $left->getGender(),
                $left->getCountry(),
                $left->getVertical(),
                $left->getPaymentModel(),
                $left->getId(),
            ] <=> [
                $right->getTrafficSource(),
                $right->getAge(),
                $right->getGender(),
                $right->getCountry(),
                $right->getVertical(),
                $right->getPaymentModel(),
                $right->getId(),
            ];
        });

        $rows = [];
        foreach ($presets as $preset) {
            $rows[] = [
                'id' => $preset->getId(),
                'country' => $preset->getCountry(),
                'trafficSource' => $preset->getTrafficSource(),
                'age' => $preset->getAge(),
                'gender' => $preset->getGender(),
                'vertical' => $preset->getVertical(),
                'paymentModel' => $preset->getPaymentModel(),
                'orders' => $this->orders($ordersByPreset[$preset->getId()] ?? [], $emails),
            ];
        }

        return $rows;
    }

    /**
     * @param list<Order> $orders
     * @param array<string, string> $emails
     * @return list<array<string, mixed>>
     */
    private function orders(array $orders, array $emails): array
    {
        usort($orders, static function (Order $left, Order $right): int {
            return [
                (int) $left->isStock(),
                $left->getServiceOn()?->format('Y-m-d') ?? '',
                $left->getId(),
            ] <=> [
                (int) $right->isStock(),
                $right->getServiceOn()?->format('Y-m-d') ?? '',
                $right->getId(),
            ];
        });

        $rows = [];
        foreach ($orders as $order) {
            $serviceOn = $order->getServiceOn();
            $rows[] = [
                'id' => $order->getId(),
                'stock' => $order->isStock(),
                'serviceOn' => $serviceOn?->format('Y-m-d'),
                'volume' => $order->getVolume(),
                'supplied' => $order->getSuppliedCount(),
                'partnerEmail' => $emails[$order->getPartnerId()] ?? '',
                'opensAt' => $order->getOpensAt(),
                'closesAt' => $order->getClosesAt(),
            ];
        }

        return $rows;
    }

    private function value(Preset $preset, RotationAxis $axis): string
    {
        return match ($axis) {
            RotationAxis::Vertical => $preset->getVertical(),
            RotationAxis::Country => $preset->getCountry(),
            RotationAxis::TrafficSource => $preset->getTrafficSource(),
            RotationAxis::PaymentModel => $preset->getPaymentModel(),
            RotationAxis::Age => $preset->getAge(),
            RotationAxis::Gender => $preset->getGender(),
        };
    }
}
