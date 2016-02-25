<?php

use ZfrEbWorker\Cli\PublisherCommand;
use ZfrEbWorker\Cli\WorkerCommand;
use ZfrEbWorker\Container\PublisherCommandFactory;
use ZfrEbWorker\Container\MessageQueueRepositoryFactory;
use ZfrEbWorker\Container\WorkerCommandFactory;
use ZfrEbWorker\Container\WorkerMiddlewareFactory;
use ZfrEbWorker\MessageQueue\MessageQueueRepository;
use ZfrEbWorker\Middleware\WorkerMiddleware;

return [
    'dependencies' => [
        'factories' => [
            PublisherCommand::class       => PublisherCommandFactory::class,
            MessageQueueRepository::class => MessageQueueRepositoryFactory::class,
            WorkerCommand::class          => WorkerCommandFactory::class,
            WorkerMiddleware::class       => WorkerMiddlewareFactory::class,
        ]
    ],
];