<?php

declare(strict_types=1);

namespace App;

use RuntimeException;
use Symfony\Component\Dotenv\Dotenv;

use function in_array;
use function is_file;
use function sprintf;

final class Environment
{
    public const string DEV = 'dev';
    public const string TEST = 'test';
    public const string PROD = 'prod';

    private const array ENVIRONMENTS = [
        self::DEV,
        self::TEST,
        self::PROD,
    ];

    public static function prepare(): void
    {
        $envFile = dirname(__DIR__) . '/.env';
        if (is_file($envFile)) {
            (new Dotenv())->usePutenv()->load($envFile);
        }

        $env = self::string('RIDER_ENV', self::DEV);
        if (!in_array($env, self::ENVIRONMENTS, true)) {
            throw new RuntimeException(sprintf('RIDER_ENV must be one of: %s.', implode(', ', self::ENVIRONMENTS)));
        }
    }

    public static function appEnv(): string
    {
        return self::string('RIDER_ENV', self::DEV);
    }

    public static function isDev(): bool
    {
        return self::appEnv() === self::DEV;
    }

    public static function isTest(): bool
    {
        return self::appEnv() === self::TEST;
    }

    public static function isProd(): bool
    {
        return self::appEnv() === self::PROD;
    }

    public static function appDebug(): bool
    {
        $value = $_ENV['RIDER_DEBUG'] ?? getenv('RIDER_DEBUG');
        if ($value === false || $value === null || $value === '') {
            return self::isDev();
        }

        return filter_var($value, FILTER_VALIDATE_BOOL);
    }

    private static function string(string $name, string $default): string
    {
        $value = $_ENV[$name] ?? getenv($name);
        if (!is_string($value) || $value === '') {
            return $default;
        }

        return $value;
    }
}
