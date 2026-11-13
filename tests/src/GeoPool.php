<?php

declare(strict_types=1);

namespace Tests;

use RuntimeException;

final readonly class GeoPool
{
    /**
     * @param array<string, true> $used
     * @return list<string>
     */
    public function take(array $used, int $count): array
    {
        $free = [];
        foreach (range('A', 'Z') as $first) {
            foreach (range('A', 'Z') as $second) {
                $code = $first . $second;
                if (isset($used[$code])) {
                    continue;
                }
                $free[] = $code;
                if (count($free) === $count) {
                    return $free;
                }
            }
        }

        throw new RuntimeException('No free geo code left');
    }
}
