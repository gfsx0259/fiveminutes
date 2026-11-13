<?php

declare(strict_types=1);

namespace App\Api;

use App\Context\Identity\CurrentUser;
use App\Context\Identity\NoAccountException;
use App\Context\Identity\NoSessionException;
use App\Context\Lead\Intake;
use App\Context\Lead\LeadJournal;
use App\Context\Lead\LeadRejectedException;
use App\Context\Lead\UnknownApiKeyException;
use App\Context\User\NotBuyerException;
use Psr\Http\Message\ResponseInterface;
use Yiisoft\DataResponse\ResponseFactory\DataResponseFactoryInterface;
use Yiisoft\RequestProvider\RequestProviderInterface;

final readonly class LeadController
{
    public function __construct(
        private Intake $intake,
        private LeadJournal $journal,
        private CurrentUser $currentUser,
        private DataResponseFactoryInterface $responses,
        private RequestProviderInterface $requests,
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
}
