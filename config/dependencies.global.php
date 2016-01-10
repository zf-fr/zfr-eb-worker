<?php

use ZfrEbWorker\Container\QueuePublisherFactory;
use ZfrEbWorker\Container\WorkerMiddlewareFactory;
use ZfrEbWorker\Publisher\QueuePublisher;
use ZfrEbWorker\Middleware\WorkerMiddleware;

return [
    'dependencies' => [
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
         * Array of job name => middleware to execute
         */

        'jobs' => []
    ]
];