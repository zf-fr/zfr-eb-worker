<?php

use ZfrEbWorker\Middleware\LocalhostCheckerMiddleware;
use ZfrEbWorker\Middleware\WorkerMiddleware;

return [
    'routes' => [
        [
            'name'       => 'internal.worker',
            'path'       => '/internal/worker',
            'middleware' => [
                LocalhostCheckerMiddleware::class,
                WorkerMiddleware::class
            ],
            'allowed_methods' => ['POST'],
        ]
    ],
];