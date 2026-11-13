<?php

declare(strict_types=1);

namespace App\Api;

use App\Context\Identity\CurrentUser;
use App\Context\Identity\NoAccountException;
use App\Context\Identity\NoSessionException;
use App\Context\User\BuyerKey;
use App\Context\User\NotBuyerException;
use App\Context\User\User;
use App\Context\User\UserRole;
use Psr\Http\Message\ResponseInterface;
use Yiisoft\DataResponse\ResponseFactory\DataResponseFactoryInterface;
use Yiisoft\RequestProvider\RequestProviderInterface;

final readonly class ApiKeyController
{
    public function __construct(
        private CurrentUser $currentUser,
        private BuyerKey $keys,
        private DataResponseFactoryInterface $responses,
        private RequestProviderInterface $requests,
    ) {}

    public function show(): ResponseInterface
    {
        try {
            $user = $this->buyer();
        } catch (NoSessionException) {
            return $this->responses->createResponse(['error' => 'No session'], 401);
        } catch (NoAccountException | NotBuyerException $exception) {
            return $this->responses->createResponse(['error' => $exception->getMessage()], 403);
        }

        return $this->responses->createResponse(['apiKey' => $user->apiKey()]);
    }

    public function create(): ResponseInterface
    {
        try {
            $user = $this->buyer();
            $key = $this->keys->issue($user);
        } catch (NoSessionException) {
            return $this->responses->createResponse(['error' => 'No session'], 401);
        } catch (NoAccountException | NotBuyerException $exception) {
            return $this->responses->createResponse(['error' => $exception->getMessage()], 403);
        }

        return $this->responses->createResponse(['apiKey' => $key->value()]);
    }

    private function buyer(): User
    {
        $user = $this->currentUser->require($this->requests->get()->getHeaderLine('Cookie'));
        if ($user->getRole() !== UserRole::Buyer) {
            throw new NotBuyerException();
        }

        return $user;
    }
}
