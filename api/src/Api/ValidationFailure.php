<?php

declare(strict_types=1);

namespace App\Api;

use Psr\Http\Message\ResponseInterface;
use Yiisoft\DataResponse\ResponseFactory\DataResponseFactoryInterface;
use Yiisoft\Input\Http\InputValidationException;

final readonly class ValidationFailure
{
    public function __construct(
        private DataResponseFactoryInterface $responses,
    ) {}

    public function __invoke(InputValidationException $exception): ResponseInterface
    {
        return $this->responses->createResponse(
            ['errors' => $exception->getResult()->getFirstErrorMessagesIndexedByPath()],
            400,
        );
    }
}
