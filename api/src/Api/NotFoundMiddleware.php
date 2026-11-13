<?php

declare(strict_types=1);

namespace App\Api;

use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;
use Yiisoft\DataResponse\ResponseFactory\DataResponseFactoryInterface;

final class NotFoundMiddleware implements MiddlewareInterface
{
    public function __construct(
        private readonly DataResponseFactoryInterface $responses,
    ) {}

    public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
    {
        return $this->responses->createResponse(['error' => 'Not found'], 404);
    }
}
