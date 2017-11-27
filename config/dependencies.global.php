<?php

use Zend\ServiceManager\Factory\InvokableFactory;
use ZfrEbWorker\Cli\PublisherCommand;
use ZfrEbWorker\Cli\WorkerCommand;
use ZfrEbWorker\Container\PublisherCommandFactory;
use ZfrEbWorker\Container\InMemoryMessageQueueRepositoryFactory;
use ZfrEbWorker\Container\WorkerCommandFactory;
use ZfrEbWorker\Container\WorkerMiddlewareFactory;
use ZfrEbWorker\Container\MessageRouterMiddlewareFactory;
use ZfrEbWorker\Container\WorkerMessageAttributesMiddlewareFactory;
use ZfrEbWorker\Listener\SilentFailingListener;
use ZfrEbWorker\MessageQueue\InMemoryMessageQueueRepository;
use ZfrEbWorker\MessageQueue\MessageQueueRepositoryInterface;
use ZfrEbWorker\Middleware\WorkerMiddleware;
use ZfrEbWorker\Middleware\MessageRouterMiddleware;
use ZfrEbWorker\Middleware\WorkerMessageAttributesMiddleware;


return [
    'dependencies' => [
        'aliases' => [
            MessageQueueRepositoryInterface::class => InMemoryMessageQueueRepository::class
        ],

        'factories' => [
            InMemoryMessageQueueRepository::class    => InMemoryMessageQueueRepositoryFactory::class,
            PublisherCommand::class                  => PublisherCommandFactory::class,
            SilentFailingListener::class             => InvokableFactory::class,
            WorkerCommand::class                     => WorkerCommandFactory::class,
            WorkerMiddleware::class                  => WorkerMiddlewareFactory::class,
            MessageRouterMiddleware::class           => MessageRouterMiddlewareFactory::class,
            WorkerMessageAttributesMiddleware::class => WorkerMessageAttributesMiddlewareFactory::class,
        ]
    ],
];
