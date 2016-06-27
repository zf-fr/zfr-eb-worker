<?php

namespace ZfrEbWorkerTest\Middleware;

use Psr\Http\Message\ServerRequestInterface;
use Zend\Diactoros\Response;
use ZfrEbWorker\Exception\RuntimeException;
use ZfrEbWorker\Middleware\LocalhostCheckerMiddleware;

class LocalhostCheckerMiddlewareTest extends \PHPUnit_Framework_TestCase
{
    public function testThrowsExceptionIfNotFromLocalhost()
    {
        $request  = $this->prophesize(ServerRequestInterface::class);
        $response = new Response();

        $request->getServerParams()->shouldBeCalled()->willReturn(['REMOTE_ADDR' => '123.43.45.242']);

        $middleware = new LocalhostCheckerMiddleware();

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage(
            'Worker requests must come from localhost, request originated from 123.43.45.242 given'
        );

        $middleware->__invoke($request->reveal(), $response, function() {
            $this->fail('Should not be called');
        });
    }

    public function dockerIpAddresses()
    {
        return [['172.17.42.1'], ['172.17.0.1']];
    }

    /**
     * @dataProvider dockerIpAddresses
     */
    public function testDelegatesIfFromDockerLocal(string $ipAddress)
    {
        $request  = $this->prophesize(ServerRequestInterface::class);
        $response = new Response();

        $request->getServerParams()->shouldBeCalled()->willReturn(['REMOTE_ADDR' => $ipAddress]);

        $middleware = new LocalhostCheckerMiddleware();

        $returnedResponse = $middleware->__invoke($request->reveal(), $response, function($request, $response) {
            return $response;
        });

        $this->assertEquals(200, $returnedResponse->getStatusCode());
    }

    public function testDelegateIfFromIPv4Localhost()
    {
        $request  = $this->prophesize(ServerRequestInterface::class);
        $response = new Response();

        $request->getServerParams()->shouldBeCalled()->willReturn(['REMOTE_ADDR' => '127.0.0.1']);

        $middleware = new LocalhostCheckerMiddleware();

        $returnedResponse = $middleware->__invoke($request->reveal(), $response, function($request, $response) {
            return $response;
        });

        $this->assertEquals(200, $returnedResponse->getStatusCode());
    }

    public function testDelegateIfFromIPv6Localhost()
    {
        $request  = $this->prophesize(ServerRequestInterface::class);
        $response = new Response();

        $request->getServerParams()->shouldBeCalled()->willReturn(['REMOTE_ADDR' => '::1']);

        $middleware = new LocalhostCheckerMiddleware();

        $returnedResponse = $middleware->__invoke($request->reveal(), $response, function($request, $response) {
            return $response;
        });

        $this->assertEquals(200, $returnedResponse->getStatusCode());
    }
}
