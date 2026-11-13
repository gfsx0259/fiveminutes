<?php

declare(strict_types=1);

namespace App\Api;

use App\Context\User\IdentityAlreadyLinkedException;
use App\Context\User\InviteMissingException;
use App\Context\User\Registration;
use InvalidArgumentException;
use JsonException;
use Psr\Http\Message\ResponseFactoryInterface;
use Psr\Http\Message\ResponseInterface;
use Yiisoft\DataResponse\ResponseFactory\DataResponseFactoryInterface;
use Yiisoft\RequestProvider\RequestProviderInterface;

final readonly class RegistrationController
{
    public function __construct(
        private Registration $registration,
        private DataResponseFactoryInterface $responses,
        private ResponseFactoryInterface $raw,
        private RequestProviderInterface $requests,
    ) {}

    public function create(): ResponseInterface
    {
        $body = $this->requests->get()->getParsedBody();
        $userId = is_array($body) && is_string($body['userId'] ?? null) ? $body['userId'] : '';
        $email = is_array($body) && is_string($body['email'] ?? null) ? $body['email'] : '';

        try {
            $this->registration->accept($userId, $email);
        } catch (InviteMissingException $exception) {
            return $this->interrupt($exception->getMessage(), 400);
        } catch (IdentityAlreadyLinkedException $exception) {
            return $this->interrupt($exception->getMessage(), 409);
        } catch (InvalidArgumentException $exception) {
            return $this->interrupt($exception->getMessage(), 400);
        }

        return $this->responses->createResponse(['status' => 'ok']);
    }

    private function interrupt(string $text, int $code): ResponseInterface
    {
        try {
            $payload = json_encode([
                'messages' => [[
                    'instance_ptr' => '#/traits/email',
                    'messages' => [[
                        'id' => 4000001,
                        'text' => $text,
                        'type' => 'error',
                    ]],
                ]],
            ], JSON_THROW_ON_ERROR);
        } catch (JsonException $exception) {
            $payload = '{"messages":[]}';
        }

        $response = $this->raw->createResponse($code);
        $response->getBody()->write($payload);

        return $response->withHeader('Content-Type', 'application/json');
    }
}
