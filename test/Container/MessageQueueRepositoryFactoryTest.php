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

use Aws\Sdk as AwsSdk;
use Aws\Sqs\SqsClient;
use Interop\Container\ContainerInterface;
use ZfrEbWorker\Container\MessageQueueRepositoryFactory;
use ZfrEbWorker\Exception\RuntimeException;

class MessageQueueRepositoryFactoryTest extends \PHPUnit_Framework_TestCase
{
    public function testThrowExceptionIfNoConfig()
    {
        $this->expectException(RuntimeException::class);

        $container = $this->prophesize(ContainerInterface::class);
        $container->get('config')->shouldBeCalled()->willReturn([]);

        $factory = new MessageQueueRepositoryFactory();

        $factory->__invoke($container->reveal());
    }

    public function testFactory()
    {
        $container = $this->prophesize(ContainerInterface::class);
        $container->get('config')->shouldBeCalled()->willReturn([
            'zfr_eb_worker' => [
                'queues' => []
            ]
        ]);

        $sqsClient = $this->prophesize(SqsClient::class);

        $awsSdk = $this->prophesize(AwsSdk::class);
        $awsSdk->createSqs()->shouldBeCalled()->willReturn($sqsClient->reveal());

        $container->get(AwsSdk::class)->shouldBeCalled()->willReturn($awsSdk->reveal());

        $factory = new MessageQueueRepositoryFactory();
        $factory->__invoke($container->reveal());
    }
}