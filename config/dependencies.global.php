<?php

use ZfrEbWorker\Container\QueuePublisherFactory;
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
            QueuePublisher::class   => QueuePublisherFactory::class,
            WorkerMiddleware::class => WorkerMiddlewareFactory::class
        ]
    ],

    'zfr_eb_worker' => [
        /**
         * Array of queue name => queue URL
         */

        'queues' => [],

        /**
         * Array of task name => middleware to execute
         */

        'tasks' => []
    ]
];