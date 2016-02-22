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

use Aws\Sqs\Exception\SqsException;
use Aws\Sqs\SqsClient;
use Prophecy\Argument;
use Prophecy\Prophecy\ObjectProphecy;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use ZfrEbWorker\Publisher\QueuePublisherInterface;

class PublisherCommandTest extends \PHPUnit_Framework_TestCase
{
    /**
     * @var \Prophecy\Prophecy\ObjectProphecy
     */
    private $queuePublisher;

    /**
     * @var \Prophecy\Prophecy\ObjectProphecy
     */
    private $sqsClient;

    /**
     * @var PublisherCommand
     */
    private $publisherCommand;

    public function setUp()
    {
        $this->queuePublisher   = $this->prophesize(QueuePublisherInterface::class);
        $this->sqsClient        = $this->prophesize(SqsClient::class);
        $this->publisherCommand = new PublisherCommand($this->queuePublisher->reveal(), $this->sqsClient->reveal());
    }

    public function testCanConfigureCommand()
    {
        $inputDefinition = $this->publisherCommand->getDefinition();

        $this->assertTrue($inputDefinition->hasOption('payload'));
        $this->assertTrue($inputDefinition->hasOption('name'));
        $this->assertTrue($inputDefinition->hasOption('queue'));
    }

    public function testThrowExceptionIfQueueDoesNotExist()
    {
        $input  = $this->prophesize(InputInterface::class);
        $output = $this->prophesize(OutputInterface::class);

        $input->getOption('name')->shouldBeCalled()->willReturn('user.created');
        $input->getOption('queue')->shouldBeCalled()->willReturn('default');

        $this->sqsClient->getQueueUrl(['QueueName' => 'default'])->shouldBeCalled()->willThrow(SqsException::class);

        $output->writeln(Argument::containingString('<error>Impossible to retrieve URL for queue "default"'))->shouldBeCalled();

        $this->executeCommand($input, $output);
    }

    public function testCanPushMessage()
    {
        $input  = $this->prophesize(InputInterface::class);
        $output = $this->prophesize(OutputInterface::class);

        $input->getOption('name')->shouldBeCalled()->willReturn('user.created');
        $input->getOption('queue')->shouldBeCalled()->willReturn('default');
        $input->getOption('payload')->shouldBeCalled()->willReturn('key=value&user[first_name]=John&user[last_name]=Doe');

        $this->sqsClient->getQueueUrl(['QueueName' => 'default'])->shouldBeCalled()->willReturn([
            'QueueUrl' => 'https://sqs.amazonaws.com'
        ]);

        $this->queuePublisher->setQueue('default', 'https://sqs.amazonaws.com')->shouldBeCalled();
        $this->queuePublisher->push('default', 'user.created', [
            'key'  => 'value',
            'user' => [
                'first_name' => 'John',
                'last_name'  => 'Doe'
            ]
        ])->shouldBeCalled();
        $this->queuePublisher->flush()->shouldBeCalled();

        $this->executeCommand($input, $output);
    }

    private function executeCommand(ObjectProphecy $input, ObjectProphecy $output)
    {
        $reflMethod = new \ReflectionMethod(PublisherCommand::class, 'execute');
        $reflMethod->setAccessible(true);

        $reflMethod->invoke($this->publisherCommand, $input->reveal(), $output->reveal());
    }
}