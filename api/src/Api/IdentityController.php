<?php

declare(strict_types=1);

namespace App\Api;

use App\Context\Identity\IdentitySettingsException;
use App\Context\Identity\KratosBrowserRedirect;
use App\Context\Identity\KratosSettings;
use App\Context\Identity\NoAccountException;
use App\Context\Identity\NoSessionException;
use Psr\Http\Message\ResponseInterface;
use Throwable;
use Yiisoft\DataResponse\ResponseFactory\DataResponseFactoryInterface;
use Yiisoft\RequestProvider\RequestProviderInterface;

final readonly class IdentityController
{
    public function __construct(
        private KratosSettings $settings,
        private DataResponseFactoryInterface $responses,
        private RequestProviderInterface $requests,
    ) {}

    public function settingsFlow(): ResponseInterface
    {
        try {
            $cookie = $this->requests->get()->getHeaderLine('Cookie');
            $query = $this->requests->get()->getQueryParams();
            $returnTo = isset($query['return_to']) && is_string($query['return_to']) ? $query['return_to'] : null;

            return $this->responses->createResponse($this->settings->beginFlow($cookie, $returnTo));
        } catch (Throwable $exception) {
            return $this->failure($exception);
        }
    }

    public function updateSettings(): ResponseInterface
    {
        try {
            $cookie = $this->requests->get()->getHeaderLine('Cookie');
            $body = $this->requests->get()->getParsedBody();
            if (!is_array($body)) {
                return $this->responses->createResponse(['error' => 'Invalid body'], 400);
            }

            $flowId = $this->stringField($body, 'flowId');
            $csrfToken = $this->stringField($body, 'csrfToken');
            $method = $this->stringField($body, 'method');

            if ($flowId === '' || $csrfToken === '' || $method === '') {
                return $this->responses->createResponse(['error' => 'flowId, csrfToken and method are required'], 400);
            }

            $result = match ($method) {
                'profile' => $this->settings->updateProfile(
                    $cookie,
                    $flowId,
                    $csrfToken,
                    $this->stringField($body, 'email'),
                ),
                'password' => $this->settings->updatePassword(
                    $cookie,
                    $flowId,
                    $csrfToken,
                    $this->stringField($body, 'password'),
                ),
                default => throw new IdentitySettingsException('Unsupported settings method'),
            };

            return $this->responses->createResponse($result);
        } catch (Throwable $exception) {
            return $this->failure($exception);
        }
    }

    public function sessions(): ResponseInterface
    {
        try {
            $cookie = $this->requests->get()->getHeaderLine('Cookie');
            $items = $this->settings->listSessions($cookie);

            return $this->responses->createResponse(['items' => $items]);
        } catch (Throwable $exception) {
            return $this->failure($exception);
        }
    }

    public function revokeSession(string $id): ResponseInterface
    {
        try {
            $cookie = $this->requests->get()->getHeaderLine('Cookie');
            $this->settings->revokeSession($cookie, $id);

            return $this->responses->createResponse(['ok' => true]);
        } catch (Throwable $exception) {
            return $this->failure($exception);
        }
    }

    public function revokeOtherSessions(): ResponseInterface
    {
        try {
            $cookie = $this->requests->get()->getHeaderLine('Cookie');
            $count = $this->settings->revokeOtherSessions($cookie);

            return $this->responses->createResponse(['count' => $count]);
        } catch (Throwable $exception) {
            return $this->failure($exception);
        }
    }

    private function stringField(array $body, string $key): string
    {
        $value = $body[$key] ?? '';
        return is_string($value) ? trim($value) : '';
    }

    private function failure(Throwable $exception): ResponseInterface
    {
        if ($exception instanceof NoSessionException) {
            return $this->responses->createResponse(['error' => 'No session'], 401);
        }
        if ($exception instanceof NoAccountException) {
            return $this->responses->createResponse(['error' => 'Account not found'], 404);
        }
        if ($exception instanceof IdentitySettingsException) {
            $payload = ['error' => $exception->getMessage()];
            $redirect = $exception->redirectBrowserTo();
            if ($redirect !== null) {
                $request = $this->requests->get();
                $clientOrigin = KratosBrowserRedirect::clientOriginFromHeaders(
                    $request->getHeaderLine('Origin'),
                    $request->getHeaderLine('Referer'),
                );
                $payload['redirectBrowserTo'] = KratosBrowserRedirect::rewrite($redirect, $clientOrigin) ?? $redirect;
                $payload['reauth'] = true;
            }

            return $this->responses->createResponse($payload, 422);
        }

        return $this->responses->createResponse(['error' => 'Request failed'], 500);
    }
}