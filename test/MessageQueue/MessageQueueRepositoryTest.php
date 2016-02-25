<?php

namespace ZfrEbWorkerTest\MessageQueue;

use Aws\Sqs\SqsClient;
use ZfrEbWorker\MessageQueue\InMemoryMessageQueueRepository;

class MessageQueueRepositoryTest extends \PHPUnit_Framework_TestCase
{
    public function testCanGetQueueByName()
    {
        $repository = new InMemoryMessageQueueRepository(['first_queue' => 'https://test.com'], $this->prophesize(SqsClient::class)->reveal());
        $repository->getMessageQueue('first_queue');
    }
}