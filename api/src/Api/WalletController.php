<?php

declare(strict_types=1);

namespace App\Api;

use App\Api\Form\WalletAdjustForm;
use App\Context\Identity\CurrentUser;
use App\Context\Identity\NoAccountException;
use App\Context\Identity\NoSessionException;
use App\Context\Wallet\InsufficientBalanceException;
use App\Context\Wallet\WalletDesk;
use App\Context\Wallet\WalletHistory;
use DomainException;
use InvalidArgumentException;
use Psr\Http\Message\ResponseInterface;
use Throwable;
use Yiisoft\DataResponse\ResponseFactory\DataResponseFactoryInterface;
use Yiisoft\RequestProvider\RequestProviderInterface;

final readonly class WalletController
{
    public function __construct(
        private CurrentUser $currentUser,
        private WalletDesk $desk,
        private WalletHistory $history,
        private DataResponseFactoryInterface $responses,
        private RequestProviderInterface $requests,
    ) {}

    public function transactions(): ResponseInterface
    {
        try {
            $user = $this->currentUser->require($this->requests->get()->getHeaderLine('Cookie'));
        } catch (Throwable $exception) {
            return $this->failure($exception);
        }

        return $this->responses->createResponse(['transactions' => $this->history->forUser($user)]);
    }

    public function deposit(WalletAdjustForm $form): ResponseInterface
    {
        return $this->adjust(fn ($user) => $this->desk->deposit($user, $form->amountCents()));
    }

    public function withdraw(WalletAdjustForm $form): ResponseInterface
    {
        return $this->adjust(fn ($user) => $this->desk->withdraw($user, $form->amountCents()));
    }

    /**
     * @param callable(\App\Context\User\User): int $action
     */
    private function adjust(callable $action): ResponseInterface
    {
        try {
            $user = $this->currentUser->require($this->requests->get()->getHeaderLine('Cookie'));
            $balanceCents = $action($user);
        } catch (Throwable $exception) {
            return $this->failure($exception);
        }

        return $this->responses->createResponse(['balanceCents' => $balanceCents]);
    }

    private function failure(Throwable $exception): ResponseInterface
    {
        if ($exception instanceof NoSessionException) {
            return $this->responses->createResponse(['error' => 'No session'], 401);
        }
        if ($exception instanceof InvalidArgumentException) {
            return $this->responses->createResponse(['error' => $exception->getMessage()], 400);
        }
        if ($exception instanceof InsufficientBalanceException) {
            return $this->responses->createResponse(['error' => $exception->getMessage()], 400);
        }
        if ($exception instanceof NoAccountException || $exception instanceof DomainException) {
            return $this->responses->createResponse(['error' => $exception->getMessage()], 403);
        }

        throw $exception;
    }
}