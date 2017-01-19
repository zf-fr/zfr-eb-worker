<?php

namespace ZfrEbWorkerTest\Message;

use ZfrEbWorker\Message\DelayedMessage;

class DelayedMessageTest extends \PHPUnit_Framework_TestCase
{
    public function testMessage()
    {
        $message = new DelayedMessage('test', ['foo' => 'bar'], 15);

        $this->assertEquals('test', $message->getName());
        $this->assertEquals(['foo' => 'bar'], $message->getPayload());
        $this->assertEquals(15, $message->getDelay());
    }
}