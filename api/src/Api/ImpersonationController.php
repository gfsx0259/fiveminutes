<?php

declare(strict_types=1);

namespace App\Api;

use App\Api\Form\ImpersonateForm;
use App\Context\Identity\CurrentUser;
use App\Context\Identity\ImpersonationCookie;
use App\Context\Identity\ImpersonationNotAllowedException;
use App\Context\Identity\ImpersonationService;
use App\Context\Identity\NoAccountException;
use App\Context\Identity\NoSessionException;
use App\Context\User\User;
use Psr\Http\Message\ResponseInterface;
use Throwable;
use Yiisoft\DataResponse\ResponseFactory\DataResponseFactoryInterface;
use Yiisoft\RequestProvider\RequestProviderInterface;

final readonly class ImpersonationController
{
    public function __construct(
        private CurrentUser $currentUser,
        private ImpersonationService $impersonation,
        private ImpersonationCookie $impersonationCookie,
        private DataResponseFactoryInterface $responses,
        private RequestProviderInterface $requests,
    ) {}

    public function start(ImpersonateForm $form): ResponseInterface
    {
        try {
            $cookie = $this->requests->get()->getHeaderLine('Cookie');
            $actor = $this->currentUser->requireActor($cookie);
            $target = $this->impersonation->start($actor, strtolower($form->userId));
        } catch (Throwable $exception) {
            return $this->failure($exception);
        }

        $secure = str_starts_with(
            $this->requests->get()->getUri()->getScheme(),
            'https',
        );

        return $this->withCookie(
            $this->responses->createResponse($this->userBody($target, $actor)),
            $this->impersonationCookie->issueHeader($target->getId(), $secure),
        );
    }

    public function stop(): ResponseInterface
    {
        try {
            $cookie = $this->requests->get()->getHeaderLine('Cookie');
            $actor = $this->currentUser->requireActor($cookie);
            $this->impersonation->stop($actor);
        } catch (Throwable $exception) {
            return $this->failure($exception);
        }

        return $this->withCookie(
            $this->responses->createResponse($this->userBody($actor, null)),
            $this->impersonationCookie->clearHeader(),
        );
    }

    /**
     * @return array<string, mixed>
     */
    private function userBody(User $user, ?User $actor): array
    {
        return [
            'user' => [
                'id' => $user->getId(),
                'email' => $user->getEmail(),
                'role' => $user->getRole()->value,
            ],
            'impersonation' => $actor === null || $actor->getId() === $user->getId() ? null : [
                'active' => true,
                'actor' => [
                    'id' => $actor->getId(),
                    'email' => $actor->getEmail(),
                ],
            ],
        ];
    }

    private function withCookie(ResponseInterface $response, string $setCookie): ResponseInterface
    {
        return $response->withAddedHeader('Set-Cookie', $setCookie);
    }

    private function failure(Throwable $exception): ResponseInterface
    {
        if ($exception instanceof NoSessionException) {
            return $this->responses->createResponse(['error' => 'No session'], 401);
        }
        if ($exception instanceof NoAccountException) {
            return $this->responses->createResponse(['error' => 'Account not found'], 404);
        }
        if ($exception instanceof ImpersonationNotAllowedException) {
            return $this->responses->createResponse(['error' => $exception->getMessage()], 403);
        }

        return $this->responses->createResponse(['error' => 'Impersonation failed'], 500);
    }
}