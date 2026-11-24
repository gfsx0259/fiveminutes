<?php

declare(strict_types=1);

namespace App\Context\Identity;

final class KratosBrowserRedirect
{
    public static function rewrite(?string $redirect, ?string $clientOrigin): ?string
    {
        if ($redirect === null || $redirect === '') {
            return $redirect;
        }

        $publicId = self::publicIdOrigin($clientOrigin);
        if ($publicId === null) {
            return $redirect;
        }

        $parts = parse_url($redirect);
        if (!is_array($parts)) {
            return $redirect;
        }

        $host = strtolower((string) ($parts['host'] ?? ''));
        if (!self::isInternalKratosHost($host)) {
            return $redirect;
        }

        $path = $parts['path'] ?? '/';
        $query = $parts['query'] ?? '';

        if ($query !== '') {
            parse_str($query, $params);
            if (isset($params['return_to']) && is_string($params['return_to'])) {
                $params['return_to'] = self::rewriteNestedUrl($params['return_to'], $publicId);
                $query = http_build_query($params);
            }
        }

        $suffix = $query !== '' ? '?' . $query : '';
        if (isset($parts['fragment']) && is_string($parts['fragment']) && $parts['fragment'] !== '') {
            $suffix .= '#' . $parts['fragment'];
        }

        return $publicId . $path . $suffix;
    }

    public static function clientOriginFromHeaders(string $origin, string $referer): ?string
    {
        if ($origin !== '') {
            return $origin;
        }
        if ($referer === '') {
            return null;
        }

        $parts = parse_url($referer);
        if (!is_array($parts) || !isset($parts['scheme'], $parts['host'])) {
            return null;
        }

        $port = isset($parts['port']) ? ':' . $parts['port'] : '';

        return $parts['scheme'] . '://' . $parts['host'] . $port;
    }

    private static function publicIdOrigin(?string $clientOrigin): ?string
    {
        if ($clientOrigin === null || $clientOrigin === '') {
            return null;
        }

        $parts = parse_url($clientOrigin);
        if (!is_array($parts) || !isset($parts['host'])) {
            return null;
        }

        $host = strtolower($parts['host']);
        $labels = explode('.', $host);
        if (count($labels) < 2) {
            return null;
        }

        $apex = $labels[count($labels) - 2] . '.' . $labels[count($labels) - 1];

        return 'https://id.' . $apex;
    }

    private static function isInternalKratosHost(string $host): bool
    {
        return str_contains($host, 'kratos')
            || $host === 'localhost'
            || str_ends_with($host, '.local');
    }

    private static function rewriteNestedUrl(string $url, string $publicId): string
    {
        $parts = parse_url($url);
        if (!is_array($parts)) {
            return $url;
        }

        $host = strtolower((string) ($parts['host'] ?? ''));
        if (!self::isInternalKratosHost($host)) {
            return $url;
        }

        $path = $parts['path'] ?? '/';
        $query = isset($parts['query']) ? '?' . $parts['query'] : '';
        $fragment = isset($parts['fragment']) ? '#' . $parts['fragment'] : '';

        return $publicId . $path . $query . $fragment;
    }
}