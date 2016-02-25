<?php

namespace ZfrEbWorker\Queue;

use Aws\Sqs\SqsClient;

/**
 * Repository that allow to easily create queue based on configuration
 *
 * @author Michaël Gallego
 */
class QueueRepository
{
    /**
     * @var array
     */
    private $queueConfig;

    /**
     * @var SqsClient
     */
    private $sqsClient;

    /**
     * @var QueueInterface[]
     */
    private $queues;

    /**
     * @param array     $queueConfig
     * @param SqsClient $sqsClient
     */
    public function __construct(array $queueConfig, SqsClient $sqsClient)
    {
        $this->queueConfig = $queueConfig;
        $this->sqsClient   = $sqsClient;
    }

    /**
     * Get a queue by its name
     *
     * @param  string $name
     * @return QueueInterface
     */
    public function getQueueByName(string $name): QueueInterface
    {
        if (isset($this->queues[$name])) {
            return $this->queues[$name];
        }

        $queueUrl            = $this->queueConfig[$name] ?? '';
        $this->queues[$name] = new Queue($this->sqsClient, $name, $queueUrl);

        return $this->queues[$name];
    }
}