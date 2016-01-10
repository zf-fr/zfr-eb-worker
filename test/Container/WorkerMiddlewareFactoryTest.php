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

namespace ZfrEbWorkerTest\Container;

use Interop\Container\ContainerInterface;
use ZfrEbWorker\Container\WorkerMiddlewareFactory;
use ZfrEbWorker\Exception\RuntimeException;
use ZfrEbWorker\Middleware\WorkerMiddleware;

class WorkerMiddlewareFactoryTest extends \PHPUnit_Framework_TestCase
{
    public function testThrowExceptionIfNoConfig()
    {
        $this->setExpectedException(RuntimeException::class);

        $container = $this->getMock(ContainerInterface::class);
        $container->expects($this->once())->method('get')->with('config')->willReturn([]);

        $factory = new WorkerMiddlewareFactory();

        $factory->__invoke($container);
    }

    public function testFactory()
    {
        $container = $this->getMock(ContainerInterface::class);
        $container->expects($this->at(0))->method('get')->with('config')->willReturn([
            'zfr_eb_worker' => [
                'tasks' => []
            ]
        ]);

        $factory        = new WorkerMiddlewareFactory();
        $queuePublisher = $factory->__invoke($container);

        $this->assertInstanceOf(WorkerMiddleware::class, $queuePublisher);
    }
}