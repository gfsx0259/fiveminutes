<?php

declare(strict_types=1);

namespace App\Api;

use App\Context\Identity\CurrentUser;
use App\Context\Identity\NoAccountException;
use App\Context\Identity\NoSessionException;
use App\Api\Form\LeadRejectForm;
use App\Context\Lead\Intake;
use App\Context\Lead\LeadConversionDesk;
use App\Context\Lead\LeadJournal;
use App\Context\Lead\LeadPartnerDeliveryDesk;
use App\Context\Lead\LeadRejectedException;
use App\Context\Lead\UnknownApiKeyException;
use App\Context\User\NotBuyerException;
use DomainException;
use InvalidArgumentException;
use Psr\Http\Message\ResponseInterface;
use Throwable;
use Yiisoft\DataResponse\ResponseFactory\DataResponseFactoryInterface;
use Yiisoft\RequestProvider\RequestProviderInterface;
use Yiisoft\Router\CurrentRoute;

final readonly class LeadController
{
    public function __construct(
        private Intake $intake,
        private LeadJournal $journal,
        private LeadConversionDesk $conversions,
        private LeadPartnerDeliveryDesk $partnerDelivery,
        private CurrentUser $currentUser,
        private DataResponseFactoryInterface $responses,
        private RequestProviderInterface $requests,
        private CurrentRoute $route,
    ) {}

    public function create(): ResponseInterface
    {
        $header = $this->requests->get()->getHeaderLine('Authorization');
        $token = str_starts_with($header, 'Bearer ') ? trim(substr($header, 7)) : '';
        $body = $this->requests->get()->getParsedBody();

        try {
            $lead = $this->intake->accept($token, is_array($body) ? $body : []);
        } catch (UnknownApiKeyException $exception) {
            return $this->responses->createResponse(['error' => $exception->getMessage()], 401);
        } catch (NotBuyerException $exception) {
            return $this->responses->createResponse(['error' => $exception->getMessage()], 403);
        } catch (LeadRejectedException $exception) {
            return $this->responses->createResponse(['errors' => $exception->errors()], 400);
        }

        return $this->responses->createResponse([
            'uuid' => $lead->getUuid(),
        ]);
    }

    public function index(): ResponseInterface
    {
        try {
            $user = $this->currentUser->require($this->requests->get()->getHeaderLine('Cookie'));
        } catch (NoSessionException) {
            return $this->responses->createResponse(['error' => 'No session'], 401);
        } catch (NoAccountException $exception) {
            return $this->responses->createResponse(['error' => $exception->getMessage()], 403);
        }

        return $this->responses->createResponse([
            'leads' => $this->journal->forUser($user),
        ]);
    }

    public function show(): ResponseInterface
    {
        try {
            $user = $this->currentUser->require($this->requests->get()->getHeaderLine('Cookie'));
        } catch (NoSessionException) {
            return $this->responses->createResponse(['error' => 'No session'], 401);
        } catch (NoAccountException $exception) {
            return $this->responses->createResponse(['error' => $exception->getMessage()], 403);
        }

        $uuid = (string) $this->route->getArgument('uuid');
        $lead = $this->journal->oneForUser($user, $uuid);
        if ($lead === null) {
            return $this->responses->createResponse(['error' => 'Lead not found'], 404);
        }

        return $this->responses->createResponse(['lead' => $lead]);
    }

    public function approve(): ResponseInterface
    {
        return $this->convert(fn ($user, $uuid) => $this->conversions->approve($user, $uuid));
    }

    public function reject(LeadRejectForm $form): ResponseInterface
    {
        return $this->convert(fn ($user, $uuid) => $this->conversions->reject($user, $uuid, $form->reason()));
    }

    public function deliver(): ResponseInterface
    {
        return $this->convert(fn ($user, $uuid) => $this->partnerDelivery->mockDeliver($user, $uuid));
    }

    /**
     * @param callable(\App\Context\User\User, string): \App\Context\Lead\Lead $action
     */
    private function convert(callable $action): ResponseInterface
    {
        try {
            $user = $this->currentUser->require($this->requests->get()->getHeaderLine('Cookie'));
            $uuid = (string) $this->route->getArgument('uuid');
            $lead = $action($user, $uuid);
        } catch (Throwable $exception) {
            return $this->conversionFailure($exception);
        }

        return $this->responses->createResponse([
            'lead' => $this->journal->oneForUser($user, $lead->getUuid()),
        ]);
    }

    private function conversionFailure(Throwable $exception): ResponseInterface
    {
        if ($exception instanceof NoSessionException) {
            return $this->responses->createResponse(['error' => 'No session'], 401);
        }
        if ($exception instanceof InvalidArgumentException) {
            return $this->responses->createResponse(['error' => $exception->getMessage()], 400);
        }
        if ($exception instanceof NoAccountException || $exception instanceof DomainException) {
            return $this->responses->createResponse(['error' => $exception->getMessage()], 403);
        }

        throw $exception;
    }
}
