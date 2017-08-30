<?php
/*
 * THIS SOFTWARE IS PROVIDED BY THE COPYRIGHT HOLDERS AND CONTRIBUTORS
 * "AS IS" AND ANY EXPRESS OR IMPLIED WARRANTIES, INCLUDING, BUT NOT
 * LIMITED TO, THE IMPLIED WARRANTIES OF MERCHANTABILITY AND FITNESS FOR
 * A PARTICULAR PURPOSE ARE DISCLAIMED. IN NO EVENT SHALL THE COPYRIGHT
 * OWNER OR CONTRIBUTORS BE LIABLE FOR ANY DIRECT, INDIRECT, INCIDENTAL,
 * SPECIAL, EXEMPLARY, OR CONSEQUENTIAL DAMAGES (INCLUDING, BUT NOT
 * LIMITED TO, PROCUREMENT OF SUBSTITUTE GOODS OR SERVICES; LOSS OF USE,
 * DATA, OR PROFITS; OR BUSINESS INTERRUPTION) HOWEVER CAUSED AND ON ANY
 * THEORY OF LIABILITY, WHETHER IN CONTRACT, STRICT LIABILITY, OR TORT
 * (INCLUDING NEGLIGENCE OR OTHERWISE) ARISING IN ANY WAY OUT OF THE USE
 * OF THIS SOFTWARE, EVEN IF ADVISED OF THE POSSIBILITY OF SUCH DAMAGE.
 *
 * This software consists of voluntary contributions made by many individuals
 * and is licensed under the MIT license.
 */

namespace ZfrEbWorkerTest\Middleware;

use DateTimeImmutable;
use Interop\Http\ServerMiddleware\DelegateInterface;
use Interop\Http\ServerMiddleware\MiddlewareInterface;
use Prophecy\Argument;
use Psr\Container\ContainerInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Zend\Diactoros\Response;
use Zend\Diactoros\ServerRequest;
use Zend\Diactoros\Stream;
use ZfrEbWorker\Exception\InvalidArgumentException;
use ZfrEbWorker\Exception\RuntimeException;
use ZfrEbWorker\Middleware\WorkerMiddleware;

class WorkerMiddlewareTest extends \PHPUnit_Framework_TestCase
{
    /**
     * @dataProvider provideIpAddresses
     *
     * @param string $ipAddress
     * @param bool   $allowed
     */
    public function testThrowsExceptionIfNotFromLocalhost(string $ipAddress, bool $allowed)
    {
        $container  = $this->prophesize(ContainerInterface::class);
        $delegate   = $this->prophesize(DelegateInterface::class);
        $middleware = new WorkerMiddleware(['message-name' => 'listener'], $container->reveal());

        $request   = $this->createRequest($ipAddress);
        $response  = new Response();

        if (!$allowed) {
            $this->expectException(RuntimeException::class);
            $this->expectExceptionMessage(sprintf(
                'Worker requests must come from localhost, request originated from %s given',
                $ipAddress
            ));

            $container->get('listener')->shouldNotBeCalled();
            $middleware->process($request, $delegate->reveal());

            return;
        }

        $middlewareListener = $this->prophesize(MiddlewareInterface::class);
        $container->get('listener')->shouldBeCalled()->willReturn($middlewareListener->reveal());
        $middlewareListener->process(Argument::type(ServerRequestInterface::class), $delegate->reveal())->shouldBeCalled()->willReturn($response);

        $returnedResponse = $middleware->process($request, $delegate->reveal());

        $this->assertSame('ZfrEbWorker', $returnedResponse->getHeaderLine('X-Handled-By'));
    }

    public function provideIpAddresses(): array
    {
        return [
            ['127.0.0.1', true],
            ['::1', true],
            ['172.17.42.1', true],
            ['172.20.42.1', true],
            ['172.18.42.1', true],
            ['172.17.0.1', true],
            ['189.55.56.131', false]
        ];
    }

    public function testThrowsExceptionIfNotSqsUserAgent()
    {
        $container  = $this->prophesize(ContainerInterface::class);
        $middleware = new WorkerMiddleware([], $container->reveal());
        $delegate   = $this->prophesize(DelegateInterface::class);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Worker requests must come from "aws-sqsd" user agent');

        $container->get(Argument::any())->shouldNotBeCalled();

        $middleware->process($this->createRequest()->withoutHeader('User-Agent'), $delegate->reveal());
    }

    public function testUserAgentIsNotCaseSensitive()
    {
        $container  = $this->prophesize(ContainerInterface::class);
        $middleware = new WorkerMiddleware(['message-name' => 'listener'], $container->reveal());
        $delegate   = $this->prophesize(DelegateInterface::class);

        $request   = $this->createRequest()->withHeader('User-Agent', 'aws-SQSD/1.2');
        $response  = new Response();

        $middlewareListener = $this->prophesize(MiddlewareInterface::class);

        $container->get('listener')->shouldBeCalled()->willReturn($middlewareListener->reveal());
        $middlewareListener->process(Argument::type(ServerRequestInterface::class), $delegate->reveal())->shouldBeCalled()->willReturn($response);

        $returnedResponse = $middleware->process($request, $delegate->reveal());

        $this->assertSame('ZfrEbWorker', $returnedResponse->getHeaderLine('X-Handled-By'));
    }

    public function testThrowsExceptionIfNoMappedMiddleware()
    {
        $container  = $this->prophesize(ContainerInterface::class);
        $middleware = new WorkerMiddleware([], $container->reveal());
        $delegate   = $this->prophesize(DelegateInterface::class);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('No middleware was mapped for message "message-name". Did you fill the "zfr_eb_worker" configuration?');

        $container->get(Argument::any())->shouldNotBeCalled();

        $middleware->process($this->createRequest(), $delegate->reveal());
    }

    public function testThrowsExceptionIfInvalidMappedMiddlewareType()
    {
        $container  = $this->prophesize(ContainerInterface::class);
        $middleware = new WorkerMiddleware(['message-name' => 10], $container->reveal());
        $delegate   = $this->prophesize(DelegateInterface::class);

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Mapped middleware must be a string, integer given.');

        $middleware->process($this->createRequest(), $delegate->reveal());
    }

    public function testThrowsExceptionIfInvalidMappedMiddlewareClass()
    {
        $container  = $this->prophesize(ContainerInterface::class);
        $middleware = new WorkerMiddleware(['message-name' => new \stdClass()], $container->reveal());
        $delegate   = $this->prophesize(DelegateInterface::class);

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Mapped middleware must be a string, stdClass given.');

        $middleware->process($this->createRequest(), $delegate->reveal());
    }

    public function testNormalizeSuccessStatusCode()
    {
        $container  = $this->prophesize(ContainerInterface::class);
        $delegate   = $this->prophesize(DelegateInterface::class);
        $middleware = new WorkerMiddleware(['message-name' => 'FooMiddleware'], $container->reveal());
        $request    = $this->createRequest('127.0.0.1', false);
        $response   = new Response\EmptyResponse();

        $middlewareListener = $this->prophesize(MiddlewareInterface::class);

        $container->get('FooMiddleware')->shouldBeCalled()->willReturn($middlewareListener->reveal());

        $middlewareListener->process(
            Argument::type(ServerRequestInterface::class), Argument::type(DelegateInterface::class)
        )->shouldBeCalled()->willReturn($response);

        /** @var ResponseInterface $returnedResponse */
        $returnedResponse = $middleware->process($request, $delegate->reveal());

        $this->assertEquals('ZfrEbWorker', $returnedResponse->getHeaderLine('X-Handled-By'), 'Make sure that it adds the X-Handled-By header');
        $this->assertEquals(200, $returnedResponse->getStatusCode(), '204 has been changed to 200 for message deletion');
    }

    /**
     * @dataProvider mappedMiddlewaresProvider
     *
     * @param string $mappedMiddleware
     * @param bool   $isPeriodicTask
     */
    public function testDispatchesMappedMiddlewareFor(string $mappedMiddleware, bool $isPeriodicTask)
    {
        $now        = new DateTimeImmutable();
        $container  = $this->prophesize(ContainerInterface::class);
        $delegate   = $this->prophesize(DelegateInterface::class);
        $middleware = new WorkerMiddleware(['message-name' => $mappedMiddleware], $container->reveal());
        $request    = $this->createRequest('127.0.0.1', $isPeriodicTask, $now);
        $response   = new Response();

        $middlewareListener = $this->prophesize(MiddlewareInterface::class);

        $container->get($mappedMiddleware)->shouldBeCalled()->willReturn($middlewareListener->reveal());

        $middlewareListener->process(Argument::that(function(ServerRequestInterface $request) use ($isPeriodicTask, $now) {
            $this->assertEquals('message-name', $request->getAttribute(WorkerMiddleware::MESSAGE_NAME_ATTRIBUTE));
            $this->assertEquals('default-queue', $request->getAttribute(WorkerMiddleware::MATCHED_QUEUE_ATTRIBUTE));
            $this->assertEquals('123abc', $request->getAttribute(WorkerMiddleware::MESSAGE_ID_ATTRIBUTE));

            if ($isPeriodicTask) {
                // Elastic Beanstalk never push any body inside a periodic task
                $this->assertEquals([], $request->getAttribute(WorkerMiddleware::MESSAGE_PAYLOAD_ATTRIBUTE));
                $this->assertEquals($now->format('c'), $request->getAttribute(WorkerMiddleware::MESSAGE_SCHEDULED_AT_ATTRIBUTE));
                $this->assertEmpty($request->getParsedBody());
            } else {
                $this->assertEquals(['id' => 123], $request->getAttribute(WorkerMiddleware::MESSAGE_PAYLOAD_ATTRIBUTE));
                $this->assertEquals('', $request->getAttribute(WorkerMiddleware::MESSAGE_SCHEDULED_AT_ATTRIBUTE));
                $this->assertEquals(['id' => 123], $request->getParsedBody());
            }

            return true;
        }), $delegate->reveal())->shouldBeCalled()->willReturn($response);

        /** @var ResponseInterface $returnedResponse */
        $returnedResponse = $middleware->process($request, $delegate->reveal());
        
        $this->assertEquals('ZfrEbWorker', $returnedResponse->getHeaderLine('X-Handled-By'), 'Make sure that it adds the X-Handled-By header');
    }

    public function mappedMiddlewaresProvider(): array
    {
        return [
            ['FooMiddleware', false],
            ['FooMiddleware', true],
        ];
    }

    private function createRequest($ipAddress = '127.0.0.1', bool $isPeriodicTask = false, DateTimeImmutable $date = null): ServerRequestInterface
    {
        $date    = $date ?? new DateTimeImmutable();
        $request = new ServerRequest(['REMOTE_ADDR' => $ipAddress]);

        $request = $request->withHeader('User-Agent', 'aws-sqsd/1.1');
        $request = $request->withHeader('X-Aws-Sqsd-Queue', 'default-queue');
        $request = $request->withHeader('X-Aws-Sqsd-Msgid', '123abc');
        $request = $request->withBody(new Stream('php://temp', 'w'));

        if ($isPeriodicTask) {
            $request = $request->withHeader('X-Aws-Sqsd-Taskname', 'message-name');
            $request = $request->withHeader('X-Aws-Sqsd-Scheduled-At', $date->format('c'));
        } else {
            $request = $request->withHeader('X-Aws-Sqsd-Attr-Name', 'message-name');
            $request->getBody()->write(json_encode(['id' => 123]));
        }

        return $request;
    }
}
