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
     * @var \PHPUnit_Framework_MockObject_MockObject
     */
    private $request;

    /**
     * @var \PHPUnit_Framework_MockObject_MockObject
     */
    private $response;

    /**
     * @var \PHPUnit_Framework_MockObject_MockObject
     */
    private $container;

    public function setUp()
    {
        $this->container = $this->getMock(ContainerInterface::class);
        $this->request   = $this->getMock(ServerRequestInterface::class);
        $this->response  = $this->getMock(ResponseInterface::class);
    }

    public function testThrowExceptionIfNoTaskMapping()
    {
        $this->setExpectedException(RuntimeException::class);

        $body = json_encode(['task_name' => 'task-name', 'attributes' => []]);
        $this->request->expects($this->once())->method('getBody')->willReturn($body);

        $middleware = new WorkerMiddleware([], $this->container);
        $middleware->__invoke($this->request, $this->response, function() {});
    }

    public function testCanDispatchToMiddleware()
    {
        $body = json_encode(['task_name' => 'task-name', 'attributes' => ['id' => 123]]);
        $this->request->expects($this->at(0))->method('getBody')->willReturn($body);

        $middleware = new WorkerMiddleware(['task-name' => 'MyMiddleware'], $this->container);

        $taskMiddleware = function($request, $response) {
          $this->assertSame($request, $this->request);
        };

        $this->container->expects($this->once())->method('get')->with('MyMiddleware')->willReturn($taskMiddleware);

        $this->request->expects($this->at(1))->method('getHeaderLine')->with('X-Aws-Sqsd-Queue')->willReturn('default-queue');
        $this->request->expects($this->at(2))->method('withAttribute')->with('worker.matched_queue', 'default-queue')->willReturnSelf();
        $this->request->expects($this->at(3))->method('getHeaderLine')->with('X-Aws-Sqsd-Msgid')->willReturn('123abc');
        $this->request->expects($this->at(4))->method('withAttribute')->with('worker.message_id', '123abc')->willReturnSelf();
        $this->request->expects($this->at(5))->method('withAttribute')->with('worker.message_body', ['id' => 123])->willReturnSelf();

        $middleware->__invoke($this->request, $this->response);
    }
}