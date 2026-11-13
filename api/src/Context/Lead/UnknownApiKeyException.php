<?php

declare(strict_types=1);

namespace App\Context\Lead;

use RuntimeException;

final class UnknownApiKeyException extends RuntimeException
{
    public function __construct()
    {
        parent::__construct('Invalid api key');
    }
}
