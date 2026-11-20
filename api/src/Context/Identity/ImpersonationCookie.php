<?php

declare(strict_types=1);

namespace App\Context\Identity;

final readonly class ImpersonationCookie
{
    public const string NAME = 'rider_impersonate';
    public const int TTL = 14_400;

    public function __construct(
        private string $secret,
    ) {}

    public function issue(string $userId): string
    {
        $exp = time() + self::TTL;
        $payload = $userId . '.' . $exp;

        return $this->sign($payload);
    }

    public function clearHeader(): string
    {
        return self::NAME . '=; Path=/; Max-Age=0; HttpOnly; SameSite=Lax';
    }

    public function issueHeader(string $userId, bool $secure): string
    {
        $value = $this->issue($userId);
        $secureFlag = $secure ? '; Secure' : '';

        return sprintf(
            '%s=%s; Path=/; Max-Age=%d; HttpOnly; SameSite=Lax%s',
            self::NAME,
            rawurlencode($value),
            self::TTL,
            $secureFlag,
        );
    }

    public function userIdFromCookieHeader(string $cookieHeader): ?string
    {
        $raw = $this->rawValue($cookieHeader);
        if ($raw === null || $raw === '') {
            return null;
        }

        return $this->validateSigned($raw);
    }

    private function rawValue(string $cookieHeader): ?string
    {
        foreach (explode(';', $cookieHeader) as $part) {
            $part = trim($part);
            $prefix = self::NAME . '=';
            if (str_starts_with($part, $prefix)) {
                return rawurldecode(substr($part, strlen($prefix)));
            }
        }

        return null;
    }

    private function validateSigned(string $value): ?string
    {
        $dot = strrpos($value, '.');
        if ($dot === false) {
            return null;
        }

        $payload = substr($value, 0, $dot);
        $sig = substr($value, $dot + 1);
        if ($payload === '' || $sig === '') {
            return null;
        }

        if (!hash_equals($this->sign($payload), $value)) {
            return null;
        }

        $parts = explode('.', $payload, 2);
        if (count($parts) !== 2) {
            return null;
        }

        [$userId, $exp] = $parts;
        if ($userId === '' || $exp === '' || (int) $exp < time()) {
            return null;
        }

        return preg_match(
            '/^[0-9a-f]{8}-[0-9a-f]{4}-[1-5][0-9a-f]{3}-[89ab][0-9a-f]{3}-[0-9a-f]{12}$/i',
            $userId,
        ) === 1 ? strtolower($userId) : null;
    }

    private function sign(string $payload): string
    {
        $sig = hash_hmac('sha256', $payload, $this->secret);

        return $payload . '.' . $sig;
    }
}