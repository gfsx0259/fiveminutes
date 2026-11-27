<?php

declare(strict_types=1);

namespace App\Context\Tree;

use DateTimeImmutable;
use Redis;
use RuntimeException;

final readonly class DeficitMatch
{
    private const int SOLD_TTL_SECONDS = 172800;

    private string $script;

    public function __construct(
        private Redis $redis,
        private KeySchema $keys,
        private float $rateExponent = 1.0,
    ) {
        $script = file_get_contents(__DIR__ . '/match_wdrr.lua');
        if ($script === false || $script === '') {
            throw new RuntimeException('WDRR script is missing');
        }
        $this->script = $script;
    }

    public function take(
        string $organizationId,
        string $presetId,
        string $geo,
        bool $stock,
        DateTimeImmutable $now,
        string $meta = '',
    ): ?Pick {
        $result = $this->redis->eval(
            $this->script,
            [
                $this->keys->pool($organizationId, $presetId),
                $now->format('N'),
                (string) (((int) $now->format('G')) * 60 + (int) $now->format('i')),
                $now->format('U'),
                (string) self::SOLD_TTL_SECONDS,
                (string) $this->rateExponent,
                $this->keys->prefix($organizationId),
                strtoupper($geo),
                $now->format('Y-m-d'),
                $stock ? 'stock' : 'day',
                $meta,
            ],
            1,
        );

        if ($result === false || $result === 'POOL_NOT_FOUND' || $result === null) {
            $error = $this->redis->getLastError();
            if (is_string($error) && $error !== '') {
                $this->redis->clearLastError();
                throw new RuntimeException('Redis eval failed: ' . $error);
            }

            return null;
        }
        if (!is_array($result) || !isset($result[0], $result[1], $result[2], $result[3])) {
            throw new RuntimeException('WDRR script returned an unexpected result');
        }

        return new Pick(
            (string) $result[0],
            (string) $result[1],
            (int) $result[2],
            (int) $result[3],
            $stock,
        );
    }
}
