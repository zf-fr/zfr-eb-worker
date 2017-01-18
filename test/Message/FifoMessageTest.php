<?php

namespace ZfrEbWorkerTest\Message;

use ZfrEbWorker\Message\FifoMessage;

class FifoMessageTest extends \PHPUnit_Framework_TestCase
{
    public function testMessage()
    {
        $message = new FifoMessage('test', ['foo' => 'bar'], 'group_id', 'deduplication_id');

        $this->assertEquals('test', $message->getName());
        $this->assertEquals(['foo' => 'bar'], $message->getPayload());
        $this->assertEquals('group_id', $message->getGroupId());
        $this->assertEquals('deduplication_id', $message->getDeduplicationId());
    }

    public function testProvideDefaultGroupId()
    {
        $message = new FifoMessage('test', ['foo' => 'bar']);

        $this->assertEquals(128, strlen($message->getGroupId()));
    }
}