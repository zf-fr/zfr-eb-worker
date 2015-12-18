<?php

use ZfrSqsWorker\Container\QueuePublisherFactory;
use ZfrSqsWorker\Publisher\QueuePublisher;

return [
    'dependencies' => [
        'factories' => [
            QueuePublisher::class => QueuePublisherFactory::class
        ]
    ],

    'zfr_sqs_worker' => [
        /**
         * Array of queue name => queue URL
         */

        'queues' => [
            //
        ],

        /**
         * Array of job name => middleware to execute
         */

        'jobs' => [
            //
        ]
    ]
];