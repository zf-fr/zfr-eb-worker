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

use Interop\Container\ContainerInterface;
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
        $middleware = new WorkerMiddleware(['message-name' => 'listener'], $container->reveal());

        $request   = $this->createRequest($ipAddress);
        $response  = new Response();

        if (!$allowed) {
            $this->expectException(RuntimeException::class);
            $this->expectExceptionMessage(sprintf(
                'Worker requests must come from localhost, request originated from %s given',
                $ipAddress
            ));

            $middleware->__invoke($request, $response, function() {
                $this->fail('$next should not be called');
            });

            return;
        }

        $container->get('listener')->shouldBeCalled()->willReturn(
            function ($request, $response) {
                return $response;
            }
        );

        $returnedResponse = $middleware->__invoke($request, $response, function() {
            $this->fail('$next should not be called');
        });

        $this->assertSame('ZfrEbWorker', $returnedResponse->getHeaderLine('X-Handled-By'));
    }

    public function provideIpAddresses(): array
    {
        return [
            ['127.0.0.1', true],
            ['::1', true],
            ['172.17.42.1', true],
            ['172.17.0.1', true],
            ['189.55.56.131', false]
        ];
    }

    public function testThrowsExceptionIfNotSqsUserAgent()
    {
        $middleware = new WorkerMiddleware([], $this->prophesize(ContainerInterface::class)->reveal());

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Worker requests must come from "aws-sqsd" user agent');

        $middleware($this->createRequest()->withoutHeader('User-Agent'), new Response(), function() {
            $this->fail('$next should not be called');
        });
    }

    public function testThrowsExceptionIfNoMappedMiddleware()
    {
        $middleware = new WorkerMiddleware([], $this->prophesize(ContainerInterface::class)->reveal());

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('No middleware was mapped for message "message-name". Did you fill the "zfr_eb_worker" configuration?');

        $middleware($this->createRequest(), new Response(), function() {
            $this->fail('$next should not be called');
        });
    }

    public function testThrowsExceptionIfInvalidMappedMiddlewareType()
    {
        $middleware = new WorkerMiddleware(['message-name' => 10], $this->prophesize(ContainerInterface::class)->reveal());

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Mapped middleware must be either a string or an array of strings, integer given.');

        $middleware($this->createRequest(), new Response(), function() {
            $this->fail('$next should not be called');
        });
    }

    public function testThrowsExceptionIfInvalidMappedMiddlewareClass()
    {
        $middleware = new WorkerMiddleware(['message-name' => new \stdClass()], $this->prophesize(ContainerInterface::class)->reveal());

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Mapped middleware must be either a string or an array of strings, stdClass given.');

        $middleware($this->createRequest(), new Response(), function() {
            $this->fail('$next should not be called');
        });
    }

    /**
     * @dataProvider mappedMiddlewaresProvider
     *
     * @param array|string $mappedMiddlewares
     * @param int          $expectedCounter
     * @param bool         $isPeriodicTask
     */
    public function testDispatchesMappedMiddlewaresFor($mappedMiddlewares, int $expectedCounter, bool $isPeriodicTask)
    {
        $container  = $this->prophesize(ContainerInterface::class);
        $middleware = new WorkerMiddleware(['message-name' => $mappedMiddlewares], $container->reveal());
        $request    = $this->createRequest('127.0.0.1', $isPeriodicTask);
        $response   = new Response();

        if (is_string($mappedMiddlewares)) {
            $mappedMiddlewares = (array) $mappedMiddlewares;
        }

        foreach ($mappedMiddlewares as $mappedMiddleware) {
            $container->get($mappedMiddleware)->shouldBeCalled()->willReturn([$this, 'incrementMiddleware']);
        }

        $out = function ($request, ResponseInterface $response) use ($expectedCounter, $isPeriodicTask) {
            $this->assertEquals('message-name', $request->getAttribute(WorkerMiddleware::MESSAGE_NAME_ATTRIBUTE));
            $this->assertEquals('default-queue', $request->getAttribute(WorkerMiddleware::MATCHED_QUEUE_ATTRIBUTE));
            $this->assertEquals('123abc', $request->getAttribute(WorkerMiddleware::MESSAGE_ID_ATTRIBUTE));
            $this->assertEquals($expectedCounter, $request->getAttribute('counter', 0));
            $this->assertEquals($expectedCounter, $response->hasHeader('counter') ? $response->getHeaderLine('counter') : 0);

            if ($isPeriodicTask) {
                // Elastic Beanstalk never push any body inside a periodic task
                $this->assertEquals([], $request->getAttribute(WorkerMiddleware::MESSAGE_PAYLOAD_ATTRIBUTE));
            } else {
                $this->assertEquals(['id' => 123], $request->getAttribute(WorkerMiddleware::MESSAGE_PAYLOAD_ATTRIBUTE));
            }

            return $response->withAddedHeader('foo', 'bar');
        };

        /** @var ResponseInterface $returnedResponse */
        $returnedResponse = $middleware($request, $response, $out);

        $this->assertEquals('bar', $returnedResponse->getHeaderLine('foo'), 'Make sure that $out was called');
        $this->assertEquals('ZfrEbWorker', $returnedResponse->getHeaderLine('X-Handled-By'), 'Make sure that it adds the X-Handled-By header');
    }

    public function incrementMiddleware(ServerRequestInterface $request, ResponseInterface $response, callable $next): ResponseInterface
    {
        $counter  = $request->getAttribute('counter', 0) + 1;
        $request  = $request->withAttribute('counter', $counter);
        $response = $response->withHeader('counter', (string) $counter);

        return $next($request, $response);
    }

    public function mappedMiddlewaresProvider(): array
    {
        return [
            [[], 0, false],
            ['FooMiddleware', 1, false],
            [['FooMiddleware'], 1, false],
            [['FooMiddleware', 'BarMiddleware'], 2, false],
            [['FooMiddleware', 'BarMiddleware', 'BazMiddleware'], 3, false],
            [[], 0, true],
            ['FooMiddleware', 1, true],
            [['FooMiddleware'], 1, true],
            [['FooMiddleware', 'BarMiddleware'], 2, true],
            [['FooMiddleware', 'BarMiddleware', 'BazMiddleware'], 3, true],
        ];
    }

    private function createRequest($ipAddress = '127.0.0.1', bool $isPeriodicTask = false): ServerRequestInterface
    {
        $request = new ServerRequest(['REMOTE_ADDR' => $ipAddress]);

        $request = $request->withHeader('User-Agent', 'aws-sqsd/1.1');
        $request = $request->withHeader('X-Aws-Sqsd-Queue', 'default-queue');
        $request = $request->withHeader('X-Aws-Sqsd-Msgid', '123abc');
        $request = $request->withBody(new Stream('php://temp', 'w'));

        if ($isPeriodicTask) {
            $request = $request->withHeader('X-Aws-Sqsd-Taskname', 'message-name');
        } else {
            $request->getBody()->write(json_encode(['name' => 'message-name', 'payload' => ['id' => 123]]));
        }

        return $request;
    }
}
