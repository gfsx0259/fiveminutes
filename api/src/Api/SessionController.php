<?php

declare(strict_types=1);

namespace App\Api;

use App\Context\Identity\CurrentUser;
use App\Context\Identity\KratosSessions;
use App\Context\Identity\NoAccountException;
use App\Context\Identity\NoSessionException;
use App\Context\Identity\SessionView;
use Psr\Http\Message\ResponseInterface;
use Yiisoft\DataResponse\ResponseFactory\DataResponseFactoryInterface;
use Yiisoft\RequestProvider\RequestProviderInterface;

final readonly class SessionController
{
    public function __construct(
        private KratosSessions $sessions,
        private CurrentUser $currentUser,
        private SessionView $view,
        private DataResponseFactoryInterface $responses,
        private RequestProviderInterface $requests,
    ) {}

    public function show(): ResponseInterface
    {
        try {
            $cookie = $this->requests->get()->getHeaderLine('Cookie');
            $session = $this->sessions->read($cookie);
            $seat = $this->currentUser->seat($cookie);
        } catch (NoSessionException|NoAccountException) {
            return $this->responses->createResponse(['error' => 'No session'], 401);
        }

        return $this->responses->createResponse($this->view->present($session, $seat));
    }
}
