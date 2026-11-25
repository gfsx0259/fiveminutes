<?php

declare(strict_types=1);

namespace App\Api;

use App\Api\Form\OrganizationMarkupForm;
use App\Api\Form\RotationAxesForm;
use App\Api\Form\ShelfAgePolicyForm;
use App\Context\Identity\CurrentUser;
use App\Context\Identity\NoAccountException;
use App\Context\Identity\NoSessionException;
use App\Context\Market\RotationAxes;
use App\Context\Market\RotationDesk;
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
        private RotationDesk $rotations,
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

    public function shelfAgePolicy(): ResponseInterface
    {
        try {
            $policy = $this->desk->shelfAgePolicy(
                $this->currentUser->require($this->requests->get()->getHeaderLine('Cookie')),
            );
        } catch (Throwable $exception) {
            return $this->failure($exception);
        }

        return $this->responses->createResponse([
            'shelfAgePolicy' => $policy->toArray(),
        ]);
    }

    public function setShelfAgePolicy(ShelfAgePolicyForm $form): ResponseInterface
    {
        try {
            $policy = $this->desk->setShelfAgePolicy(
                $this->currentUser->require($this->requests->get()->getHeaderLine('Cookie')),
                $form->payload(),
            );
        } catch (Throwable $exception) {
            return $this->failure($exception);
        }

        return $this->responses->createResponse([
            'shelfAgePolicy' => $policy->toArray(),
        ]);
    }

    public function rotation(): ResponseInterface
    {
        try {
            $picture = $this->rotations->picture(
                $this->currentUser->require($this->requests->get()->getHeaderLine('Cookie')),
            );
        } catch (Throwable $exception) {
            return $this->failure($exception);
        }

        return $this->responses->createResponse($picture);
    }

    public function setRotation(RotationAxesForm $form): ResponseInterface
    {
        try {
            $user = $this->currentUser->require($this->requests->get()->getHeaderLine('Cookie'));
            $this->desk->setRotationAxes($user, RotationAxes::fromList($form->axes()));
            $picture = $this->rotations->picture($user);
        } catch (Throwable $exception) {
            return $this->failure($exception);
        }

        return $this->responses->createResponse($picture);
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