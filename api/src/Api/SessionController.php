<?php

declare(strict_types=1);

namespace App\Api;

use App\Context\Identity\KratosSessions;
use App\Context\Identity\NoSession;
use App\Context\Identity\SessionView;
use Psr\Http\Message\ResponseInterface;
use Yiisoft\DataResponse\ResponseFactory\DataResponseFactoryInterface;
use Yiisoft\RequestProvider\RequestProviderInterface;

final class SessionController
{
    public function __construct(
        private KratosSessions $sessions,
        private SessionView $view,
        private DataResponseFactoryInterface $responses,
        private RequestProviderInterface $requests,
    ) {}

    public function show(): ResponseInterface
    {
        try {
            $session = $this->sessions->read($this->requests->get()->getHeaderLine('Cookie'));
        } catch (NoSession) {
            return $this->responses->createResponse(['error' => 'No session'], 401);
        }

        return $this->responses->createResponse($this->view->present($session));
    }
}
