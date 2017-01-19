<?php

namespace ZfrEbWorkerTest\Message;

use ZfrEbWorker\Message\Message;

class MessageTest extends \PHPUnit_Framework_TestCase
{
    public function testMessage()
    {
        $message = new Message('test', ['foo' => 'bar']);

        $this->assertEquals('test', $message->getName());
        $this->assertEquals(['foo' => 'bar'], $message->getPayload());
        $this->assertEquals(128, strlen($message->getGroupId()));
    }
}