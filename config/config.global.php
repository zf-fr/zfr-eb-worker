<?php

use ZfrEbWorker\Cli\PublisherCommand;
use ZfrEbWorker\Cli\WorkerCommand;

return [
    'zfr_eb_worker' => [
        /**
         * Array of queue name => queue URL
         */

        'queues' => [],

        /**
         * Array of messages names => middlewares to execute
         */

        'messages' => []
    ],

    'console' => [
        'commands' => [
            WorkerCommand::class,
            PublisherCommand::class
        ]
    ],
];