<?php

declare(strict_types=1);

namespace App\Context\Market;

use DateTimeImmutable;
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

    public function availabilityUtc(): string
    {
        $start = ((int) $this->opens->format('G')) * 60 + (int) $this->opens->format('i');
        $end = ((int) $this->closes->format('G')) * 60 + (int) $this->closes->format('i');
        $parts = [];
        for ($day = 1; $day <= 7; $day++) {
            $parts[] = $day . ':' . $start . '-' . $end;
        }

        return implode(',', $parts);
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
