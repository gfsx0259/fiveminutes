<?php

declare(strict_types=1);

use App\Api\NotFoundMiddleware;
use App\Api\ValidationFailure;
use Psr\Http\Message\ResponseFactoryInterface;
use Yiisoft\DataResponse\Formatter\JsonFormatter;
use Yiisoft\DataResponse\Middleware\ContentNegotiatorDataResponseMiddleware;
use Yiisoft\Definitions\DynamicReference;
use Yiisoft\Definitions\Reference;
use Yiisoft\ErrorHandler\Middleware\ErrorCatcher;
use Yiisoft\ErrorHandler\Middleware\ExceptionResponder;
use Yiisoft\Injector\Injector;
use Yiisoft\Input\Http\HydratorAttributeParametersResolver;
use Yiisoft\Input\Http\InputValidationException;
use Yiisoft\Input\Http\RequestInputParametersResolver;
use Yiisoft\Middleware\Dispatcher\CompositeParametersResolver;
use Yiisoft\Middleware\Dispatcher\MiddlewareDispatcher;
use Yiisoft\Middleware\Dispatcher\ParametersResolverInterface;
use Yiisoft\Request\Body\RequestBodyParser;
use Yiisoft\Router\Middleware\Router;
use Yiisoft\Yii\Http\Application;

return [
    Application::class => [
        '__construct()' => [
            'dispatcher' => DynamicReference::to([
                'class' => MiddlewareDispatcher::class,
                'withMiddlewares()' => [
                    [
                        static fn() => new ContentNegotiatorDataResponseMiddleware(
                            formatters: [
                                'application/json' => new JsonFormatter(),
                            ],
                            fallback: new JsonFormatter(),
                        ),
                        ErrorCatcher::class,
                        static fn (
                            Injector $injector,
                            ResponseFactoryInterface $responseFactory,
                            ValidationFailure $failure,
                        ) => new ExceptionResponder(
                            [InputValidationException::class => $failure],
                            $responseFactory,
                            $injector,
                        ),
                        RequestBodyParser::class,
                        Router::class,
                        NotFoundMiddleware::class,
                    ],
                ],
            ]),
        ],
    ],

    ParametersResolverInterface::class => [
        'class' => CompositeParametersResolver::class,
        '__construct()' => [
            Reference::to(HydratorAttributeParametersResolver::class),
            Reference::to(RequestInputParametersResolver::class),
        ],
    ],
];
