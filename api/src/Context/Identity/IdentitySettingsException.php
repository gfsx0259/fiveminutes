<?php

declare(strict_types=1);

namespace App\Context\Identity;

use RuntimeException;
use Throwable;

final class IdentitySettingsException extends RuntimeException
{
    public function __construct(
        string $message,
        private readonly ?string $redirectBrowserTo = null,
        ?Throwable $previous = null,
    ) {
        parent::__construct($message, 0, $previous);
    }

    public function redirectBrowserTo(): ?string
    {
        return $this->redirectBrowserTo;
    }
}