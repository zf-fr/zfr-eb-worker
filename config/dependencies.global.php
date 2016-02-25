<?php

use ZfrEbWorker\Cli\PublisherCommand;
use ZfrEbWorker\Cli\WorkerCommand;
use ZfrEbWorker\Container\PublisherCommandFactory;
use ZfrEbWorker\Container\InMemoryMessageQueueRepositoryFactory;
use ZfrEbWorker\Container\WorkerCommandFactory;
use ZfrEbWorker\Container\WorkerMiddlewareFactory;
use ZfrEbWorker\MessageQueue\InMemoryMessageQueueRepository;
use ZfrEbWorker\MessageQueue\MessageQueueRepositoryInterface;
use ZfrEbWorker\Middleware\WorkerMiddleware;

return [
    'dependencies' => [
        'aliases' => [
            MessageQueueRepositoryInterface::class => InMemoryMessageQueueRepository::class
        ],

        'factories' => [
            PublisherCommand::class               => PublisherCommandFactory::class,
            InMemoryMessageQueueRepository::class => InMemoryMessageQueueRepositoryFactory::class,
            WorkerCommand::class                  => WorkerCommandFactory::class,
            WorkerMiddleware::class               => WorkerMiddlewareFactory::class,
        ]
    ],
];