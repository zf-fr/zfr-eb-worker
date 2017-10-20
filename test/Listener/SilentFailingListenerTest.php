<?php

namespace ZfrEbWorkerTest\Listener;

use Psr\Http\Message\ServerRequestInterface;
use Webimpress\HttpMiddlewareCompatibility\HandlerInterface as DelegateInterface;
use Webimpress\HttpMiddlewareCompatibility\MiddlewareInterface;
use ZfrEbWorker\Listener\SilentFailingListener;

/**
 * @author Daniel Gimenes
 */
class SilentFailingListenerTest extends \PHPUnit_Framework_TestCase
{
    public function testReturnsSuccessResponse()
    {
        $request  = $this->prophesize(ServerRequestInterface::class);
        $listener = new SilentFailingListener();

        $response = $listener->process($request->reveal(), $this->prophesize(DelegateInterface::class)->reveal());

        $this->assertEquals(200, $response->getStatusCode());
    }
}
