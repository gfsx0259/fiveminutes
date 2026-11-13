<?php

declare(strict_types=1);

namespace App\Context\Identity;

use Ory\Client\Api\FrontendApi;
use Ory\Client\ApiException;
use Ory\Client\Model\Session;

final readonly class KratosSessions
{
    public function __construct(private FrontendApi $ory) {}

    public function read(string $cookie): Session
    {
        if (trim($cookie) === '') {
            throw new NoSessionException('No session');
        }

        try {
            $session = $this->ory->toSession('', $cookie);
        } catch (ApiException $exception) {
            if (in_array($exception->getCode(), [401, 403], true)) {
                throw new NoSessionException('No session');
            }

            throw $exception;
        }

        if (!$session instanceof Session || $session->getActive() !== true) {
            throw new NoSessionException('No session');
        }

        return $session;
    }
}
