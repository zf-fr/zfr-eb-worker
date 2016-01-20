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
use ZfrEbWorker\Exception\RuntimeException;
use ZfrEbWorker\Middleware\WorkerMiddleware;

class WorkerMiddlewareTest extends \PHPUnit_Framework_TestCase
{
    /**
     * @var \Prophecy\Prophecy\ObjectProphecy
     */
    private $request;

    /**
     * @var \Prophecy\Prophecy\ObjectProphecy
     */
    private $response;

    /**
     * @var \Prophecy\Prophecy\ObjectProphecy
     */
    private $container;

    public function setUp()
    {
        $this->container = $this->prophesize(ContainerInterface::class);
        $this->request   = $this->prophesize(ServerRequestInterface::class);
        $this->response  = $this->prophesize(ResponseInterface::class);
    }

    public function testThrowExceptionIfNoTaskMapping()
    {
        $this->setExpectedException(RuntimeException::class);

        $body = json_encode(['name' => 'event-name', 'payload' => []]);
        $this->request->getBody()->shouldBeCalled()->willReturn($body);

        $middleware = new WorkerMiddleware([], $this->container->reveal());
        $middleware->__invoke($this->request->reveal(), $this->response->reveal(), function() {});
    }

    public function testCanDispatchToMiddleware()
    {
        $body = json_encode(['name' => 'event-name', 'payload' => ['id' => 123]]);
        $this->request->getBody()->shouldBeCalled()->willReturn($body);

        $middleware = new WorkerMiddleware(['event-name' => 'MyMiddleware'], $this->container->reveal());

        $messageMiddleware = function($request, $response) {
          $this->assertSame($request, $this->request->reveal());
        };

        $this->container->get('MyMiddleware')->shouldBeCalled()->willReturn($messageMiddleware);

        $this->request->getHeaderLine('X-Aws-Sqsd-Queue')->shouldBeCalled()->willReturn('default-queue');
        $this->request->withAttribute('worker.matched_queue', 'default-queue')->shouldBeCalled()->willReturn($this->request->reveal());
        $this->request->getHeaderLine('X-Aws-Sqsd-Msgid')->shouldBeCalled()->willReturn('123abc');
        $this->request->withAttribute('worker.message_id', '123abc')->shouldBeCalled()->willReturn($this->request->reveal());
        $this->request->withAttribute('worker.message_payload', ['id' => 123])->shouldBeCalled()->willReturn($this->request->reveal());
        $this->request->withAttribute('worker.message_name', 'event-name')->shouldBeCalled()->willReturn($this->request->reveal());

        $middleware->__invoke($this->request->reveal(), $this->response->reveal());
    }
}