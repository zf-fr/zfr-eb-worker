<?php

use ZfrSqsWorker\Container\QueuePublisherFactory;
use ZfrSqsWorker\Container\WorkerMiddlewareFactory;
use ZfrSqsWorker\Publisher\QueuePublisher;
use ZfrSqsWorker\WorkerMiddleware;

return [
    'dependencies' => [
        'factories' => [
            QueuePublisher::class   => QueuePublisherFactory::class,
            WorkerMiddleware::class => WorkerMiddlewareFactory::class
        ]
    ],

    'zfr_sqs_worker' => [
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