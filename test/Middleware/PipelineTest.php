<?php

namespace ZfrEbWorkerTest\Middleware;

use Interop\Container\ContainerInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Zend\Diactoros\Response;
use Zend\Diactoros\ServerRequest;
use ZfrEbWorker\Middleware\Pipeline;

/**
 * @author Daniel Gimenes
 */
final class PipelineTest extends \PHPUnit_Framework_TestCase
{
    public function testAllowsMiddlewareToReturnVoid()
    {
        $container = $this->prophesize(ContainerInterface::class);
        $pipeline  = new Pipeline($container->reveal(), ['foo']);

        $container->get('foo')->shouldBeCalled()->willReturn(
            function (ServerRequestInterface $request, ResponseInterface $response) {
                // void
            }
        );

        $passedResponse   = new Response();
        $returnedResponse = $pipeline(new ServerRequest(), $passedResponse);

        self::assertSame($passedResponse, $returnedResponse);
    }

    public function testReturnsResponseIfNoOutProvided()
    {
        $container = $this->prophesize(ContainerInterface::class);
        $pipeline  = new Pipeline($container->reveal(), ['foo']);

        $responseFromMiddleware = new Response();

        $container->get('foo')->shouldBeCalled()->willReturn(
            function (ServerRequestInterface $request, ResponseInterface $response) use ($responseFromMiddleware) {
                return $responseFromMiddleware;
            }
        );

        $returnedResponse = $pipeline(new ServerRequest(), new Response());

        self::assertSame($responseFromMiddleware, $returnedResponse);
    }

    public function testDelegatesToOut()
    {
        $container       = $this->prophesize(ContainerInterface::class);
        $passedRequest   = new ServerRequest();
        $passedResponse  = new Response();
        $responseFromOut = new Response();
        $out             = function (ServerRequestInterface $request, ResponseInterface $response) use (
            $passedRequest,
            $passedResponse,
            $responseFromOut
        ) {
            self::assertSame($passedRequest, $request);
            self::assertSame($passedResponse, $response);

            return $responseFromOut;
        };

        $pipeline         = new Pipeline($container->reveal(), [], $out);
        $returnedResponse = $pipeline($passedRequest, $passedResponse);

        self::assertSame($responseFromOut, $returnedResponse);
    }
}
