<?php

declare(strict_types=1);

namespace App\Api;

use App\Clock;
use App\Context\Identity\CurrentUser;
use App\Context\Identity\NoAccountException;
use App\Context\Identity\NoSessionException;
use App\Context\Lead\IntegrationCheck;
use App\Context\Lead\LeadJournal;
use App\Context\Team\Seat;
use App\Context\User\NotBuyerException;
use App\Context\User\UserRole;
use DateTimeImmutable;
use Exception;
use Psr\Http\Message\ResponseInterface;
use Yiisoft\DataResponse\ResponseFactory\DataResponseFactoryInterface;
use Yiisoft\RequestProvider\RequestProviderInterface;

final readonly class IntegrationController
{
    public function __construct(
        private CurrentUser $currentUser,
        private IntegrationCheck $check,
        private LeadJournal $journal,
        private Clock $clock,
        private DataResponseFactoryInterface $responses,
        private RequestProviderInterface $requests,
    ) {}

    public function show(): ResponseInterface
    {
        try {
            $seat = $this->buyer();
        } catch (NoSessionException) {
            return $this->responses->createResponse(['error' => 'No session'], 401);
        } catch (NoAccountException | NotBuyerException $exception) {
            return $this->responses->createResponse(['error' => $exception->getMessage()], 403);
        }

        return $this->responses->createResponse([
            'completedAt' => $seat->getAccount()->integrationCompletedAt(),
            'now' => $this->clock->now()->format(DATE_ATOM),
        ]);
    }

    /**
     * Asks whether a test lead of this buyer has come through, optionally only one that arrived since a moment
     * taken from the server clock. The cabinet repeats the question until the answer is a lead.
     */
    public function testLead(): ResponseInterface
    {
        $since = $this->requests->get()->getQueryParams()['since'] ?? null;
        $from = null;
        if (is_string($since) && $since !== '') {
            try {
                $from = new DateTimeImmutable($since);
            } catch (Exception) {
                return $this->responses->createResponse(['error' => 'The since moment is invalid'], 400);
            }
        }

        try {
            $seat = $this->buyer();
            $lead = $this->check->verify($seat->getAccount(), $from);
        } catch (NoSessionException) {
            return $this->responses->createResponse(['error' => 'No session'], 401);
        } catch (NoAccountException | NotBuyerException $exception) {
            return $this->responses->createResponse(['error' => $exception->getMessage()], 403);
        }

        return $this->responses->createResponse([
            'lead' => $lead === null ? null : $this->journal->oneForSeat($seat, $lead->getUuid()),
            'completedAt' => $seat->getAccount()->integrationCompletedAt(),
            'now' => $this->clock->now()->format(DATE_ATOM),
        ]);
    }

    private function buyer(): Seat
    {
        $seat = $this->currentUser->seat($this->requests->get()->getHeaderLine('Cookie'));
        if ($seat->getAccount()->getRole() !== UserRole::Buyer) {
            throw new NotBuyerException();
        }

        return $seat;
    }
}
