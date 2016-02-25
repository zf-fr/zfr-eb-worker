<?php

namespace ZfrEbWorkerTest\Message;

use ZfrEbWorker\Exception\RuntimeException;
use ZfrEbWorker\Message\DelayedMessage;

class DelayedMessageTest extends \PHPUnit_Framework_TestCase
{
    public function testMessage()
    {
        $message = new DelayedMessage('test', ['foo' => 'bar'], 60);

        $this->assertEquals('test', $message->getName());
        $this->assertEquals(['foo' => 'bar'], $message->getPayload());
        $this->assertEquals(60, $message->getDelay());
    }

    public function testThrowExceptionIfExceedsDelay()
    {
        $this->expectException(RuntimeException::class);
        new DelayedMessage('test', ['foo' => 'bar'], 10000);
    }
}