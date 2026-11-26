<?php

declare(strict_types=1);

namespace App\Api;

use App\Api\Form\WalletAdjustForm;
use App\Context\Identity\CurrentUser;
use App\Context\Identity\NoAccountException;
use App\Context\Identity\NoSessionException;
use App\Context\Team\Permission;
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
            $seat = $this->currentUser->seat($this->requests->get()->getHeaderLine('Cookie'));
            $seat->authorize(Permission::WalletView);
            if ($seat->unitId() !== null) {
                throw new DomainException('The account wallet is kept above your team, your spend is on the Team page');
            }
            $transactions = $this->history->forUser($seat->getAccount());
        } catch (Throwable $exception) {
            return $this->failure($exception);
        }

        return $this->responses->createResponse(['transactions' => $transactions]);
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
            $seat = $this->currentUser->seat($this->requests->get()->getHeaderLine('Cookie'));
            $seat->authorize(Permission::WalletManage);
            if ($seat->unitId() !== null) {
                throw new DomainException('The account balance is managed above your team');
            }
            $balanceCents = $action($seat->getAccount());
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