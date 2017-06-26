<?php

namespace ZfrEbWorkerTest\Listener;

use Interop\Http\ServerMiddleware\DelegateInterface;
use Psr\Http\Message\ServerRequestInterface;
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
