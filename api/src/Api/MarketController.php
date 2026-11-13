<?php

declare(strict_types=1);

namespace App\Api;

use App\Api\Form\DemandForm;
use App\Api\Form\SlotForm;
use App\Context\Identity\CurrentUser;
use App\Context\Identity\NoAccountException;
use App\Context\Identity\NoSessionException;
use App\Context\Market\CabinetListings;
use App\Context\Market\ListingMissingException;
use App\Context\Market\Posting;
use App\Context\User\User;
use App\Context\User\UserRole;
use DomainException;
use InvalidArgumentException;
use Psr\Http\Message\ResponseInterface;
use Throwable;
use Yiisoft\DataResponse\ResponseFactory\DataResponseFactoryInterface;
use Yiisoft\RequestProvider\RequestProviderInterface;
use Yiisoft\Router\CurrentRoute;

final readonly class MarketController
{
    public function __construct(
        private CurrentUser $currentUser,
        private Posting $posting,
        private CabinetListings $listings,
        private DataResponseFactoryInterface $responses,
        private RequestProviderInterface $requests,
        private CurrentRoute $route,
    ) {}

    public function slots(): ResponseInterface
    {
        try {
            $user = $this->user();
        } catch (NoSessionException | NoAccountException $exception) {
            return $this->sessionFailure($exception);
        }

        return $this->responses->createResponse(['slots' => $this->listings->slots($user)]);
    }

    public function openSlot(SlotForm $form): ResponseInterface
    {
        try {
            $user = $this->requireRole(UserRole::Buyer, 'Only a buyer can open a slot');
            $slot = $this->posting->openSlot(
                $user,
                $form->geo(),
                $form->volume(),
                $form->priceCents(),
                $form->pricingModel(),
                $form->deadlineAt(),
            );
        } catch (NoSessionException | NoAccountException | DomainException $exception) {
            return $this->sessionFailure($exception);
        } catch (InvalidArgumentException $exception) {
            return $this->responses->createResponse(['error' => $exception->getMessage()], 400);
        }

        return $this->responses->createResponse(['id' => $slot->getId(), 'status' => $slot->getStatus()->value]);
    }

    public function acceptSlot(): ResponseInterface
    {
        try {
            $user = $this->requireRole(UserRole::Partner, 'Only a partner can accept a slot');
            $acceptance = $this->posting->acceptSlot($user, $this->id());
        } catch (NoSessionException | NoAccountException | DomainException | ListingMissingException $exception) {
            return $this->sessionFailure($exception);
        }

        return $this->responses->createResponse(['id' => $acceptance->getId(), 'status' => 'taken']);
    }

    public function demands(): ResponseInterface
    {
        try {
            $user = $this->user();
        } catch (NoSessionException | NoAccountException $exception) {
            return $this->sessionFailure($exception);
        }

        return $this->responses->createResponse(['demands' => $this->listings->demands($user)]);
    }

    public function openDemand(DemandForm $form): ResponseInterface
    {
        try {
            $user = $this->requireRole(UserRole::Partner, 'Only a partner can open a demand');
            $demand = $this->posting->openDemand(
                $user,
                $form->geo(),
                $form->volume(),
                $form->priceCents(),
                $form->pricingModel(),
                $form->serviceOn(),
                $form->opensAt(),
                $form->closesAt(),
            );
        } catch (NoSessionException | NoAccountException | DomainException $exception) {
            return $this->sessionFailure($exception);
        } catch (InvalidArgumentException $exception) {
            return $this->responses->createResponse(['error' => $exception->getMessage()], 400);
        }

        return $this->responses->createResponse(['id' => $demand->getId(), 'status' => $demand->getStatus()->value]);
    }

    public function acceptDemand(): ResponseInterface
    {
        try {
            $user = $this->requireRole(UserRole::Buyer, 'Only a buyer can accept a demand');
            $acceptance = $this->posting->acceptDemand($user, $this->id());
        } catch (NoSessionException | NoAccountException | DomainException | ListingMissingException $exception) {
            return $this->sessionFailure($exception);
        }

        return $this->responses->createResponse(['id' => $acceptance->getId(), 'status' => 'taken']);
    }

    private function user(): User
    {
        return $this->currentUser->require($this->requests->get()->getHeaderLine('Cookie'));
    }

    private function requireRole(UserRole $role, string $message): User
    {
        $user = $this->user();
        if ($user->getRole() !== $role) {
            throw new DomainException($message);
        }

        return $user;
    }

    private function id(): string
    {
        return (string) $this->route->getArgument('id');
    }

    private function sessionFailure(Throwable $exception): ResponseInterface
    {
        if ($exception instanceof NoSessionException) {
            return $this->responses->createResponse(['error' => 'No session'], 401);
        }
        if ($exception instanceof ListingMissingException) {
            return $this->responses->createResponse(['error' => $exception->getMessage()], 404);
        }
        if ($exception instanceof DomainException && str_contains($exception->getMessage(), 'not open')) {
            return $this->responses->createResponse(['error' => $exception->getMessage()], 409);
        }

        return $this->responses->createResponse(['error' => $exception->getMessage()], 403);
    }
}
