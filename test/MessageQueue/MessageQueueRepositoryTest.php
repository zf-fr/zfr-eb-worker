<?php

namespace ZfrEbWorkerTest\MessageQueue;

use Aws\Sqs\SqsClient;
use ZfrEbWorker\MessageQueue\MessageQueueRepository;

class MessageQueueRepositoryTest extends \PHPUnit_Framework_TestCase
{
    public function testCanGetQueueByName()
    {
        $repository = new MessageQueueRepository(['first_queue' => 'https://test.com'], $this->prophesize(SqsClient::class)->reveal());
        $repository->getQueueByName('first_queue');
    }
}