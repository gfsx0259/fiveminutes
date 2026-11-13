<?php

declare(strict_types=1);

namespace Tests;

use GuzzleHttp\Client;
use GuzzleHttp\Cookie\CookieJar;
use RuntimeException;

final readonly class KratosLogin
{
    public function __construct(private Client $http) {}

    public function sessionCookie(string $email, string $password): string
    {
        $flow = json_decode((string) $this->http->get('/self-service/login/browser', [
            'headers' => ['Accept' => 'application/json'],
        ])->getBody(), true);
        $csrf = '';
        foreach ($flow['ui']['nodes'] ?? [] as $node) {
            if (($node['attributes']['name'] ?? '') === 'csrf_token') {
                $csrf = (string) ($node['attributes']['value'] ?? '');
            }
        }

        $this->http->post((string) $flow['ui']['action'], [
            'headers' => [
                'Accept' => 'application/json',
                'Content-Type' => 'application/json',
            ],
            'json' => [
                'csrf_token' => $csrf,
                'identifier' => $email,
                'password' => $password,
                'method' => 'password',
            ],
        ]);

        $cookies = $this->http->getConfig('cookies');
        if (!$cookies instanceof CookieJar) {
            throw new RuntimeException('Kratos login has no cookie jar');
        }
        foreach ($cookies as $cookie) {
            if ($cookie->getName() === 'ory_kratos_session') {
                return $cookie->getValue();
            }
        }

        throw new RuntimeException('Kratos did not open a session for ' . $email);
    }
}
