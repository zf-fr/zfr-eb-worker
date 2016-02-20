<?php

use ZfrEbWorker\Cli\PublisherCommand;
use ZfrEbWorker\Cli\WorkerCommand;
use ZfrEbWorker\Container\PublisherCommandFactory;
use ZfrEbWorker\Container\QueuePublisherFactory;
use ZfrEbWorker\Container\WorkerCommandFactory;
use ZfrEbWorker\Container\WorkerMiddlewareFactory;
use ZfrEbWorker\Publisher\QueuePublisher;
use ZfrEbWorker\Middleware\WorkerMiddleware;
use ZfrEbWorker\Publisher\QueuePublisherInterface;

return [
    'dependencies' => [
        'aliases' => [
            QueuePublisherInterface::class => QueuePublisher::class
        ],

        'factories' => [
            PublisherCommand::class => PublisherCommandFactory::class,
            QueuePublisher::class   => QueuePublisherFactory::class,
            WorkerCommand::class    => WorkerCommandFactory::class,
            WorkerMiddleware::class => WorkerMiddlewareFactory::class,
        ]
    ],
];