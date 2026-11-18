<?php

declare(strict_types=1);

namespace App\Api;

use App\Api\Form\OrganizationMarkupForm;
use App\Context\Identity\CurrentUser;
use App\Context\Identity\NoAccountException;
use App\Context\Identity\NoSessionException;
use App\Context\Organization\OrganizationDesk;
use DomainException;
use InvalidArgumentException;
use Psr\Http\Message\ResponseInterface;
use Throwable;
use Yiisoft\DataResponse\ResponseFactory\DataResponseFactoryInterface;
use Yiisoft\RequestProvider\RequestProviderInterface;

final readonly class OrganizationController
{
    public function __construct(
        private CurrentUser $currentUser,
        private OrganizationDesk $desk,
        private DataResponseFactoryInterface $responses,
        private RequestProviderInterface $requests,
    ) {}

    public function setMarkup(OrganizationMarkupForm $form): ResponseInterface
    {
        try {
            $organization = $this->desk->setMarkupPercent(
                $this->currentUser->require($this->requests->get()->getHeaderLine('Cookie')),
                $form->markupPercent(),
            );
        } catch (Throwable $exception) {
            return $this->failure($exception);
        }

        return $this->responses->createResponse([
            'markupPercent' => $organization->getMarkupPercent(),
        ]);
    }

    private function failure(Throwable $exception): ResponseInterface
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