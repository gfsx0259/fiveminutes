<?php

declare(strict_types=1);

namespace App\Api;

use App\Api\Form\OfferForm;
use App\Api\Form\OrderForm;
use App\Api\Form\PresetForm;
use App\Api\Form\StockQuoteForm;
use App\Context\Identity\CurrentUser;
use App\Context\Identity\NoAccountException;
use App\Context\Identity\NoSessionException;
use App\Context\Market\Cabinet;
use App\Context\Market\OfferDesk;
use App\Context\Market\OfferMissingException;
use App\Context\Market\OrderDesk;
use App\Context\Market\OrderMissingException;
use App\Context\Market\PresetDesk;
use App\Context\Market\PresetMissingException;
use App\Context\Market\Warehouse;
use App\Context\User\User;
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
        private PresetDesk $presets,
        private OrderDesk $orders,
        private OfferDesk $offers,
        private Cabinet $cabinet,
        private Warehouse $warehouse,
        private DataResponseFactoryInterface $responses,
        private RequestProviderInterface $requests,
        private CurrentRoute $route,
    ) {}

    public function presets(): ResponseInterface
    {
        return $this->list(fn (User $user): array => ['presets' => $this->cabinet->presets($user)]);
    }

    public function openPreset(PresetForm $form): ResponseInterface
    {
        try {
            $preset = $this->presets->open(
                $this->user(),
                $form->country(),
                $form->trafficSource(),
                $form->age(),
                $form->gender(),
                $form->vertical(),
                $form->paymentModel(),
                $form->cr(),
                $form->basePriceCents(),
            );
        } catch (Throwable $exception) {
            return $this->failure($exception);
        }

        return $this->responses->createResponse(['id' => $preset->getId()]);
    }

    public function deletePreset(): ResponseInterface
    {
        try {
            $this->presets->remove($this->user(), $this->id());
        } catch (Throwable $exception) {
            return $this->failure($exception);
        }

        return $this->responses->createResponse(['ok' => true]);
    }

    public function orders(): ResponseInterface
    {
        return $this->list(fn (User $user): array => ['orders' => $this->cabinet->orders($user)]);
    }

    public function openOrder(OrderForm $form): ResponseInterface
    {
        try {
            $order = $this->orders->open(
                $this->user(),
                $form->presetId(),
                $form->geo(),
                $form->volume(),
                $form->priceCents(),
                $form->paymentModel(),
                $form->stock(),
                $form->serviceOn(),
                $form->opensAt(),
                $form->closesAt(),
                $form->stockLeadGender(),
                $form->stockPickPreference(),
                $form->timezone(),
            );
        } catch (Throwable $exception) {
            return $this->failure($exception);
        }

        return $this->responses->createResponse([
            'id' => $order->getId(),
            'status' => $order->getStatus()->value,
            'supplied' => $order->getSuppliedCount(),
        ]);
    }

    public function warehouse(): ResponseInterface
    {
        return $this->list(function (User $user): array {
            $bundle = $this->warehouse->inventory($user);

            return [
                'shelfAgePolicy' => $bundle['shelfAgePolicy'],
                'stock' => $bundle['stock'],
            ];
        });
    }

    public function stockQuote(StockQuoteForm $form): ResponseInterface
    {
        try {
            $quote = $this->warehouse->quote(
                $this->user(),
                $form->presetId(),
                $form->quantity(),
                $form->stockLeadGender(),
                $form->stockPickPreference(),
            );
        } catch (Throwable $exception) {
            return $this->failure($exception);
        }

        return $this->responses->createResponse($quote);
    }

    public function pauseOrder(): ResponseInterface
    {
        return $this->changeOrder(fn (User $user, string $id) => $this->orders->pause($user, $id));
    }

    public function resumeOrder(): ResponseInterface
    {
        return $this->changeOrder(fn (User $user, string $id) => $this->orders->resume($user, $id));
    }

    public function finishOrder(): ResponseInterface
    {
        return $this->changeOrder(fn (User $user, string $id) => $this->orders->finish($user, $id));
    }

    public function caps(): ResponseInterface
    {
        return $this->list(fn (User $user): array => ['caps' => $this->cabinet->caps($user)]);
    }

    public function offers(): ResponseInterface
    {
        return $this->list(fn (User $user): array => ['offers' => $this->cabinet->offers($user)]);
    }

    public function openOffer(OfferForm $form): ResponseInterface
    {
        try {
            $offer = $this->offers->open(
                $this->user(),
                $form->presetId(),
                $form->geo(),
                $form->volume(),
                $form->stock(),
                $form->serviceOn(),
            );
        } catch (Throwable $exception) {
            return $this->failure($exception);
        }

        return $this->responses->createResponse(['id' => $offer->getId(), 'status' => $offer->getStatus()->value]);
    }

    public function approveOffer(): ResponseInterface
    {
        return $this->changeOffer(fn (User $user, string $id) => $this->offers->approve($user, $id));
    }

    public function rejectOffer(): ResponseInterface
    {
        return $this->changeOffer(fn (User $user, string $id) => $this->offers->reject($user, $id));
    }

    /**
     * @param callable(User): array<string, mixed> $body
     */
    private function list(callable $body): ResponseInterface
    {
        try {
            return $this->responses->createResponse($body($this->user()));
        } catch (Throwable $exception) {
            return $this->failure($exception);
        }
    }

    /**
     * @param callable(User, string): \App\Context\Market\Order $change
     */
    private function changeOrder(callable $change): ResponseInterface
    {
        try {
            $order = $change($this->user(), $this->id());
        } catch (Throwable $exception) {
            return $this->failure($exception);
        }

        return $this->responses->createResponse(['id' => $order->getId(), 'status' => $order->getStatus()->value]);
    }

    /**
     * @param callable(User, string): \App\Context\Market\Offer $change
     */
    private function changeOffer(callable $change): ResponseInterface
    {
        try {
            $offer = $change($this->user(), $this->id());
        } catch (Throwable $exception) {
            return $this->failure($exception);
        }

        return $this->responses->createResponse(['id' => $offer->getId(), 'status' => $offer->getStatus()->value]);
    }

    private function user(): User
    {
        return $this->currentUser->require($this->requests->get()->getHeaderLine('Cookie'));
    }

    private function id(): string
    {
        return (string) $this->route->getArgument('id');
    }

    private function conflict(string $message): bool
    {
        foreach (['not in progress', 'not paused', 'not pending', 'already finished', 'no room', 'cannot be removed'] as $needle) {
            if (str_contains($message, $needle)) {
                return true;
            }
        }

        return false;
    }

    private function failure(Throwable $exception): ResponseInterface
    {
        if ($exception instanceof NoSessionException) {
            return $this->responses->createResponse(['error' => 'No session'], 401);
        }
        if ($exception instanceof PresetMissingException || $exception instanceof OrderMissingException || $exception instanceof OfferMissingException) {
            return $this->responses->createResponse(['error' => $exception->getMessage()], 404);
        }
        if ($exception instanceof InvalidArgumentException) {
            return $this->responses->createResponse(['error' => $exception->getMessage()], 400);
        }
        if ($exception instanceof DomainException && $this->conflict($exception->getMessage())) {
            return $this->responses->createResponse(['error' => $exception->getMessage()], 409);
        }
        if ($exception instanceof NoAccountException || $exception instanceof DomainException) {
            return $this->responses->createResponse(['error' => $exception->getMessage()], 403);
        }

        throw $exception;
    }
}
