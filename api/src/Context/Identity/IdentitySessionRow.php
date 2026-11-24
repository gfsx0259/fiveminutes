<?php

declare(strict_types=1);

namespace App\Context\Identity;

use Ory\Client\Model\Session;
use Ory\Client\Model\SessionDevice;

final readonly class IdentitySessionRow
{
    /**
     * @return array<string, mixed>
     */
    public static function fromKratos(Session $session, bool $current): array
    {
        $device = null;
        $devices = $session->getDevices();
        if (is_array($devices)) {
            foreach ($devices as $item) {
                if ($item instanceof SessionDevice) {
                    $device = $item;
                    break;
                }
            }
        }

        $agent = (string) ($device?->getUserAgent() ?? '');
        $parsed = self::parseUserAgent($agent);

        $authenticatedAt = $session->getAuthenticatedAt();
        $lastActive = $authenticatedAt?->format(\DateTimeInterface::ATOM) ?? '';

        return [
            'id' => (string) $session->getId(),
            'device' => $parsed['device'],
            'browser' => $parsed['browser'],
            'kind' => $parsed['kind'],
            'location' => (string) ($device?->getLocation() ?? ''),
            'ip' => (string) ($device?->getIpAddress() ?? ''),
            'userAgent' => $agent,
            'lastActive' => $lastActive,
            'expiresAt' => $session->getExpiresAt()?->format(\DateTimeInterface::ATOM),
            'current' => $current,
        ];
    }

    /**
     * @return array{device: string, browser: string, kind: string}
     */
    private static function parseUserAgent(string $userAgent): array
    {
        if (trim($userAgent) === '') {
            return ['device' => 'Unknown device', 'browser' => 'Unknown browser', 'kind' => 'desktop'];
        }

        $lower = strtolower($userAgent);
        $browser = match (true) {
            str_contains($lower, 'edg/') => 'Edge',
            str_contains($lower, 'firefox/') => 'Firefox',
            str_contains($lower, 'chrome/') && !str_contains($lower, 'edg/') => 'Chrome',
            str_contains($lower, 'safari/') && !str_contains($lower, 'chrome/') => 'Safari',
            default => 'Browser',
        };

        $kind = match (true) {
            str_contains($lower, 'iphone') || str_contains($lower, 'android') && str_contains($lower, 'mobile') => 'phone',
            str_contains($lower, 'ipad') || str_contains($lower, 'tablet') => 'phone',
            str_contains($lower, 'macintosh') || str_contains($lower, 'mac os') => 'laptop',
            default => 'desktop',
        };

        $device = match (true) {
            str_contains($lower, 'iphone') => 'iPhone',
            str_contains($lower, 'ipad') => 'iPad',
            str_contains($lower, 'android') => 'Android',
            str_contains($lower, 'macintosh') || str_contains($lower, 'mac os') => 'Mac',
            str_contains($lower, 'windows') => 'Windows PC',
            str_contains($lower, 'linux') => 'Linux',
            default => 'Device',
        };

        return ['device' => $device, 'browser' => $browser, 'kind' => $kind];
    }
}