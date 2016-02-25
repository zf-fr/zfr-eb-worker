<?php

namespace ZfrEbWorker\MessageQueue;

use Aws\Sqs\SqsClient;

/**
 * Repository that allow to easily create queue based on configuration
 *
 * @author Michaël Gallego
 */
class MessageQueueRepository
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
     * @var MessageQueueInterface[]
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
     * @return MessageQueueInterface
     */
    public function getQueueByName(string $name): MessageQueueInterface
    {
        if (isset($this->queues[$name])) {
            return $this->queues[$name];
        }

        $queueUrl            = $this->queueConfig[$name] ?? '';
        $this->queues[$name] = new MessageQueue($name, $queueUrl, $this->sqsClient);

        return $this->queues[$name];
    }
}
