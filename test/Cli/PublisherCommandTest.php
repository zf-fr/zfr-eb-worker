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

namespace ZfrEbWorker\Cli;

use Prophecy\Argument;
use Prophecy\Prophecy\ObjectProphecy;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use ZfrEbWorker\Message\Message;
use ZfrEbWorker\MessageQueue\MessageQueueInterface;
use ZfrEbWorker\MessageQueue\InMemoryMessageQueueRepository;

class PublisherCommandTest extends \PHPUnit_Framework_TestCase
{
    /**
     * @var \Prophecy\Prophecy\ObjectProphecy
     */
    private $queueRepository;

    /**
     * @var PublisherCommand
     */
    private $publisherCommand;

    public function setUp()
    {
        $this->queueRepository  = $this->prophesize(InMemoryMessageQueueRepository::class);
        $this->publisherCommand = new PublisherCommand($this->queueRepository->reveal());
    }

    public function testCanConfigureCommand()
    {
        $inputDefinition = $this->publisherCommand->getDefinition();

        $this->assertTrue($inputDefinition->hasOption('payload'));
        $this->assertTrue($inputDefinition->hasOption('name'));
        $this->assertTrue($inputDefinition->hasOption('queue'));
    }

    public function testCanPushMessage()
    {
        $input  = $this->prophesize(InputInterface::class);
        $output = $this->prophesize(OutputInterface::class);

        $input->getOption('name')->shouldBeCalled()->willReturn('user.created');
        $input->getOption('queue')->shouldBeCalled()->willReturn('default');
        $input->getOption('payload')->shouldBeCalled()->willReturn('key=value&user[first_name]=John&user[last_name]=Doe');

        $payload = [
            'key'  => 'value',
            'user' => [
                'first_name' => 'John',
                'last_name'  => 'Doe'
            ]
        ];

        $queue = $this->prophesize(MessageQueueInterface::class);
        $queue->push(Argument::any())->shouldBeCalled();
        $queue->flush()->shouldBeCalled();

        $this->queueRepository->getMessageQueue('default')->shouldBeCalled()->willReturn($queue->reveal());

        $this->executeCommand($input, $output);
    }

    private function executeCommand(ObjectProphecy $input, ObjectProphecy $output)
    {
        $reflMethod = new \ReflectionMethod(PublisherCommand::class, 'execute');
        $reflMethod->setAccessible(true);

        $reflMethod->invoke($this->publisherCommand, $input->reveal(), $output->reveal());
    }
}