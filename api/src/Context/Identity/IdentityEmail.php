<?php

declare(strict_types=1);

namespace App\Context\Identity;

use Ory\Client\Model\Identity;

final readonly class IdentityEmail
{
    private ?string $value;

    public function __construct(?Identity $identity)
    {
        $traits = $identity?->getTraits();
        $email = match (true) {
            is_array($traits) => $traits['email'] ?? null,
            is_object($traits) => $traits->email ?? null,
            default => null,
        };

        $this->value = is_string($email) && $email !== '' ? $email : null;
    }

    public function value(): ?string
    {
        return $this->value;
    }
}
