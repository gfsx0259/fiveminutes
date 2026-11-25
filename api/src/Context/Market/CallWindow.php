<?php

declare(strict_types=1);

namespace App\Context\Market;

use DateTimeImmutable;
use DateTimeZone;
use InvalidArgumentException;

final readonly class CallWindow
{
    private DateTimeImmutable $opens;

    private DateTimeImmutable $closes;

    public function __construct(string $opens, string $closes)
    {
        $opens = $this->clock($opens);
        $closes = $this->clock($closes);
        if ($opens >= $closes) {
            throw new InvalidArgumentException('Call center must close after it opens');
        }
        $this->opens = $this->moment($opens);
        $this->closes = $this->moment($closes);
    }

    public function opensAt(): DateTimeImmutable
    {
        return $this->opens;
    }

    public function closesAt(): DateTimeImmutable
    {
        return $this->closes;
    }

    public function opens(): string
    {
        return $this->opens->format('H:i');
    }

    public function closes(): string
    {
        return $this->closes->format('H:i');
    }

    public function availabilityUtc(string $timezone = 'UTC', ?\DateTimeInterface $on = null): string
    {
        $ranges = $this->utcRanges($timezone, $on);
        $parts = [];
        for ($day = 1; $day <= 7; $day++) {
            foreach ($ranges as [$shift, $start, $end]) {
                $dow = (($day - 1 + $shift) % 7) + 1;
                $parts[] = $dow . ':' . $start . '-' . $end;
            }
        }

        return implode(',', $parts);
    }

    /**
     * Local hours shifted into UTC minutes. A window that crosses midnight
     * becomes the evening of this weekday and the morning of the next.
     *
     * @return list<array{0: int, 1: int, 2: int}>
     */
    private function utcRanges(string $timezone, ?\DateTimeInterface $on): array
    {
        try {
            $zone = new DateTimeZone($timezone);
        } catch (\Exception) {
            $zone = new DateTimeZone('UTC');
        }
        $utc = new DateTimeZone('UTC');
        $day = $on === null
            ? (new DateTimeImmutable('now', $zone))->format('Y-m-d')
            : DateTimeImmutable::createFromInterface($on)->setTimezone(new DateTimeZone('UTC'))->format('Y-m-d');
        $open = (new DateTimeImmutable($day . ' ' . $this->opens() . ':00', $zone))->setTimezone($utc);
        $close = (new DateTimeImmutable($day . ' ' . $this->closes() . ':00', $zone))->setTimezone($utc);
        $start = ((int) $open->format('G')) * 60 + (int) $open->format('i');
        $end = ((int) $close->format('G')) * 60 + (int) $close->format('i');
        if ($open->format('Y-m-d') === $close->format('Y-m-d')) {
            return [[0, $start, $end]];
        }

        return [
            [0, $start, 24 * 60],
            [1, 0, $end],
        ];
    }

    private function clock(string $value): string
    {
        if (preg_match('/^([01]\d|2[0-3]):[0-5]\d$/', $value) !== 1) {
            throw new InvalidArgumentException('Call center hour must be HH:MM');
        }

        return $value . ':00';
    }

    private function moment(string $value): DateTimeImmutable
    {
        $time = DateTimeImmutable::createFromFormat('!H:i:s', $value);
        if ($time === false) {
            throw new InvalidArgumentException('Call center hour must be HH:MM');
        }

        return $time;
    }
}
