<?php

declare(strict_types=1);

namespace App\Api;

use App\Context\Identity\CurrentUser;
use App\Context\Identity\NoAccountException;
use App\Context\Identity\NoSessionException;
use App\Context\User\User;
use App\Context\User\UserRepository;
use App\Context\User\UserRole;
use DomainException;
use Psr\Http\Message\ResponseInterface;
use Throwable;
use Yiisoft\DataResponse\ResponseFactory\DataResponseFactoryInterface;
use Yiisoft\RequestProvider\RequestProviderInterface;

final readonly class UserController
{
    public function __construct(
        private CurrentUser $currentUser,
        private UserRepository $users,
        private DataResponseFactoryInterface $responses,
        private RequestProviderInterface $requests,
    ) {}

    public function index(): ResponseInterface
    {
        try {
            $admin = $this->currentUser->requireActor(
                $this->requests->get()->getHeaderLine('Cookie'),
            );
            if ($admin->getRole() !== UserRole::Admin) {
                throw new DomainException('Forbidden');
            }

            $items = [];
            foreach ($this->users->findByOrganization($admin->getOrganizationId()) as $member) {
                if (!$member instanceof User) {
                    continue;
                }
                $items[] = [
                    'id' => $member->getId(),
                    'email' => $member->getEmail(),
                    'role' => $member->getRole()->value,
                ];
            }

            usort(
                $items,
                static fn (array $left, array $right): int => strcmp($left['email'], $right['email']),
            );
        } catch (Throwable $exception) {
            return $this->failure($exception);
        }

        return $this->responses->createResponse(['items' => $items]);
    }

    private function failure(Throwable $exception): ResponseInterface
    {
        if ($exception instanceof NoSessionException) {
            return $this->responses->createResponse(['error' => 'No session'], 401);
        }
        if ($exception instanceof NoAccountException) {
            return $this->responses->createResponse(['error' => 'Account not found'], 404);
        }
        if ($exception instanceof DomainException) {
            return $this->responses->createResponse(['error' => $exception->getMessage()], 403);
        }

        return $this->responses->createResponse(['error' => 'Request failed'], 500);
    }
}