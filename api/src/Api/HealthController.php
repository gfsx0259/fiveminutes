<?php

declare(strict_types=1);

namespace App\Api;

use Psr\Http\Message\ResponseInterface;
use Yiisoft\DataResponse\ResponseFactory\DataResponseFactoryInterface;

final readonly class HealthController
{
    public function __construct(
        private readonly DataResponseFactoryInterface $responses,
    ) {}

    public function index(): ResponseInterface
    {
        return $this->responses->createResponse(['status' => 'ok']);
    }
}
