<?php

declare(strict_types=1);

namespace App\Context\Identity;

use GuzzleHttp\Client;
use RuntimeException;

final class KratosSessions
{
    public function __construct(
        private Client $http,
        private string $publicUrl,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function read(string $cookie): array
    {
        if (trim($cookie) === '') {
            throw new NoSession('No session');
        }

        $response = $this->http->get(rtrim($this->publicUrl, '/') . '/sessions/whoami', [
            'headers' => [
                'Accept' => 'application/json',
                'Cookie' => $cookie,
            ],
        ]);

        if ($response->getStatusCode() !== 200) {
            throw new NoSession('No session');
        }

        $payload = json_decode((string) $response->getBody(), true);
        if (!is_array($payload)) {
            throw new RuntimeException('Kratos session is not JSON');
        }

        /** @var array<string, mixed> $payload */
        return $payload;
    }
}
