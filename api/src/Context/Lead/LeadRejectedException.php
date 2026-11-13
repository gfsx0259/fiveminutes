<?php

declare(strict_types=1);

namespace App\Context\Lead;

use InvalidArgumentException;

final class LeadRejectedException extends InvalidArgumentException
{
    /**
     * @param array<string, string> $errors
     */
    public function __construct(private array $errors)
    {
        parent::__construct('Lead was rejected');
    }

    /**
     * @return array<string, string>
     */
    public function errors(): array
    {
        return $this->errors;
    }
}
