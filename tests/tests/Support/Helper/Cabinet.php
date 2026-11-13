<?php

declare(strict_types=1);

namespace Tests\Support\Helper;

use Codeception\Module;
use Codeception\Module\REST;
use GuzzleHttp\Client;
use GuzzleHttp\Cookie\CookieJar;
use Tests\KratosLogin;

class Cabinet extends Module
{
    public function signIn(string $who): void
    {
        $email = (string) ($this->config[$who] ?? $who);
        $cookie = (new KratosLogin($this->kratos()))->sessionCookie($email, (string) $this->config['password']);
        $rest = $this->rest();
        $rest->deleteHeader('Authorization');
        $rest->haveHttpHeader('Cookie', 'ory_kratos_session=' . $cookie);
        $rest->haveHttpHeader('Accept', 'application/json');
        $rest->haveHttpHeader('Content-Type', 'application/json');
    }

    public function asGuest(): void
    {
        $rest = $this->rest();
        $rest->deleteHeader('Cookie');
        $rest->deleteHeader('Authorization');
        $rest->haveHttpHeader('Accept', 'application/json');
        $rest->haveHttpHeader('Content-Type', 'application/json');
    }

    /**
     * @return array<string, mixed>
     */
    public function waitForLead(string $uuid, string $status): array
    {
        $rest = $this->rest();
        $deadline = microtime(true) + 15;
        $last = null;
        while (microtime(true) < $deadline) {
            $rest->sendGet('/leads');
            $body = json_decode($rest->grabResponse(), true);
            foreach (is_array($body) ? ($body['leads'] ?? []) : [] as $lead) {
                if (!is_array($lead) || ($lead['uuid'] ?? '') !== $uuid) {
                    continue;
                }
                $last = $lead;
                if (($lead['status'] ?? '') === $status) {
                    return $lead;
                }
            }
            usleep(300000);
        }

        $this->fail('Lead ' . $uuid . ' did not become ' . $status . '. Last: ' . json_encode($last));
    }

    private function rest(): REST
    {
        $rest = $this->getModule('REST');
        if (!$rest instanceof REST) {
            $this->fail('REST module is not loaded');
        }

        return $rest;
    }

    private function kratos(): Client
    {
        return new Client([
            'base_uri' => (string) $this->config['kratos'],
            'cookies' => new CookieJar(),
            'verify' => false,
            'http_errors' => true,
        ]);
    }
}
