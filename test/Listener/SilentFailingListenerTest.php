<?php

namespace ZfrEbWorkerTest\Listener;

use Psr\Http\Message\ResponseInterface;
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
        $response = $this->prophesize(ResponseInterface::class);
        $listener = new SilentFailingListener();

        // Creates a response with status code 200
        $successResponse = $this->prophesize(ResponseInterface::class)->reveal();

        $response->withStatus(200)->shouldBeCalled()->willReturn($successResponse);

        // It should not call $next
        $next = function () {
            $this->fail('$next should not be called!');
        };

        $returnedResponse = $listener($request->reveal(), $response->reveal(), $next);

        $this->assertSame($successResponse, $returnedResponse);
    }
}
