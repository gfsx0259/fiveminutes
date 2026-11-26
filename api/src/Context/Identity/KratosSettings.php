<?php

declare(strict_types=1);

namespace App\Context\Identity;

use Ory\Client\Api\FrontendApi;
use Ory\Client\ApiException;
use Ory\Client\Model\Session;
use Ory\Client\Model\SettingsFlow;
use Ory\Client\Model\UpdateSettingsFlowWithPasswordMethod;
use Ory\Client\Model\UpdateSettingsFlowWithProfileMethod;

final readonly class KratosSettings
{
    public function __construct(
        private FrontendApi $ory,
        private KratosSessions $sessions,
    ) {}

    /**
     * @return array{flowId: string, csrfToken: string, email: string|null}
     */
    public function beginFlow(string $cookie, ?string $returnTo = null): array
    {
        $this->sessions->read($cookie);

        try {
            $flow = $this->ory->createBrowserSettingsFlow($returnTo, $cookie);
        } catch (ApiException $exception) {
            throw $this->map($exception);
        }

        if (!$flow instanceof SettingsFlow) {
            throw new IdentitySettingsException('Settings flow unavailable');
        }

        return $this->presentFlow($flow);
    }

    /**
     * @return array{flowId: string, csrfToken: string, email: string|null, state?: string}
     */
    public function updateProfile(string $cookie, string $flowId, string $csrfToken, string $email): array
    {
        $this->sessions->read($cookie);

        $body = new UpdateSettingsFlowWithProfileMethod();
        $body->setMethod('profile');
        $body->setCsrfToken($csrfToken);
        $body->setTraits((object) ['email' => $email]);

        try {
            $flow = $this->ory->updateSettingsFlow($flowId, $body, null, $cookie);
        } catch (ApiException $exception) {
            throw $this->map($exception);
        }

        if (!$flow instanceof SettingsFlow) {
            throw new IdentitySettingsException('Settings update failed');
        }

        return $this->presentFlow($flow);
    }

    /**
     * @return array{flowId: string, csrfToken: string, email: string|null, state?: string}
     */
    public function updatePassword(string $cookie, string $flowId, string $csrfToken, string $password): array
    {
        $this->sessions->read($cookie);

        $body = new UpdateSettingsFlowWithPasswordMethod();
        $body->setMethod('password');
        $body->setCsrfToken($csrfToken);
        $body->setPassword($password);

        try {
            $flow = $this->ory->updateSettingsFlow($flowId, $body, null, $cookie);
        } catch (ApiException $exception) {
            throw $this->map($exception);
        }

        if (!$flow instanceof SettingsFlow) {
            throw new IdentitySettingsException('Password update failed');
        }

        return $this->presentFlow($flow);
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function listSessions(string $cookie): array
    {
        $current = $this->sessions->read($cookie);
        $currentId = (string) $current->getId();

        try {
            $sessions = $this->ory->listMySessions(null, null, null, null, null, $cookie);
        } catch (ApiException $exception) {
            throw $this->map($exception);
        }

        if (!is_array($sessions)) {
            return [];
        }

        $items = [];
        foreach ($sessions as $session) {
            if (!$session instanceof Session) {
                continue;
            }
            $items[] = IdentitySessionRow::fromKratos($session, $session->getId() === $currentId);
        }

        usort(
            $items,
            static fn (array $left, array $right): int => ($right['current'] <=> $left['current'])
                ?: strcmp((string) $right['lastActive'], (string) $left['lastActive']),
        );

        return $items;
    }

    public function revokeSession(string $cookie, string $sessionId): void
    {
        $this->sessions->read($cookie);

        try {
            $this->ory->disableMySession($sessionId, null, $cookie);
        } catch (ApiException $exception) {
            throw $this->map($exception);
        }
    }

    public function revokeOtherSessions(string $cookie): int
    {
        $this->sessions->read($cookie);

        try {
            $result = $this->ory->disableMyOtherSessions(null, $cookie);
        } catch (ApiException $exception) {
            throw $this->map($exception);
        }

        return (int) ($result?->getCount() ?? 0);
    }

    /**
     * @return array{flowId: string, csrfToken: string, email: string|null, state?: string}
     */
    private function presentFlow(SettingsFlow $flow): array
    {
        $email = (new IdentityEmail($flow->getIdentity()))->value();

        $state = $flow->getState();
        $payload = [
            'flowId' => (string) $flow->getId(),
            'csrfToken' => $this->csrfFromFlow($flow),
            'email' => $email,
        ];
        if (is_string($state) && $state !== '') {
            $payload['state'] = $state;
        }

        return $payload;
    }

    private function csrfFromFlow(SettingsFlow $flow): string
    {
        $ui = $flow->getUi();
        if ($ui === null) {
            return '';
        }

        $nodes = $ui->getNodes();
        if (!is_array($nodes)) {
            return '';
        }

        foreach ($nodes as $node) {
            $attrs = $node?->getAttributes();
            $name = $attrs?->getName();
            if ($name === 'csrf_token') {
                $value = $attrs->getValue();
                return is_string($value) ? $value : '';
            }
        }

        return '';
    }

    private function map(ApiException $exception): IdentitySettingsException
    {
        if (!in_array($exception->getCode(), [400, 401, 403, 410, 422], true)) {
            return new IdentitySettingsException('Identity request failed', previous: $exception);
        }

        $raw = (string) $exception->getResponseBody();
        $decoded = json_decode($raw, true);
        $message = 'Request failed';
        $redirect = null;

        if (is_array($decoded)) {
            if (isset($decoded['error']['message']) && is_string($decoded['error']['message'])) {
                $message = $decoded['error']['message'];
            }
            if (isset($decoded['redirect_browser_to']) && is_string($decoded['redirect_browser_to'])) {
                $redirect = $decoded['redirect_browser_to'];
            }
            if ($redirect === null && isset($decoded['ui']['messages']) && is_array($decoded['ui']['messages'])) {
                foreach ($decoded['ui']['messages'] as $uiMessage) {
                    if (is_array($uiMessage) && isset($uiMessage['text']) && is_string($uiMessage['text'])) {
                        $message = $uiMessage['text'];
                        break;
                    }
                }
            }
        }

        return new IdentitySettingsException($message, $redirect, $exception);
    }
}