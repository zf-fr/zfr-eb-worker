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

namespace ZfrEbWorkerTest\MessageQueue;

use Aws\Sqs\SqsClient;
use Prophecy\Argument;
use ZfrEbWorker\Message\DelayedMessage;
use ZfrEbWorker\Message\Message;
use ZfrEbWorker\MessageQueue\MessageQueue;

class MessageQueueTest extends \PHPUnit_Framework_TestCase
{
    /**
     * @var \Prophecy\Prophecy\ObjectProphecy
     */
    private $sqsClient;

    public function setUp()
    {
        $this->sqsClient = $this->prophesize(SqsClient::class);
    }

    public function pushMode()
    {
        return [
            ['async' => true],
            ['async' => false]
        ];
    }

    /**
     * @dataProvider pushMode
     */
    public function testCanPushToQueue(bool $async)
    {
        $expectedPayload = [
            'QueueUrl' => 'https://queue-url.aws.com',
            'Entries'  => [
                [
                    'Id' => 0,
                    'MessageAttributes' => [
                        'name' => [
                            'DataType'    => 'String',
                            'StringValue' => 'message-name',
                        ],
                    ],
                    'DelaySeconds' => 0,
                    'MessageBody'  => '{"id":123,"url":"https://www.test.com"}'
                ]
            ]
        ];

        if ($async) {
            $this->sqsClient->sendMessageBatchAsync($expectedPayload)->shouldBeCalled();
        } else {
            $this->sqsClient->sendMessageBatch($expectedPayload)->shouldBeCalled();
        }

        $queue = new MessageQueue('https://queue-url.aws.com', $this->sqsClient->reveal());
        $queue->push(new Message('message-name', ['id' => 123, 'url' => 'https://www.test.com']));
        $queue->flush($async);
    }

    public function testCanPushDelayedMessage()
    {
        $expectedPayload = [
            'QueueUrl' => 'https://queue-url.aws.com',
            'Entries'  => [
                [
                    'Id' => 0,
                    'MessageAttributes' => [
                        'name' => [
                            'DataType'    => 'String',
                            'StringValue' => 'message-name',
                        ],
                    ],
                    'DelaySeconds' => 30,
                    'MessageBody'  => '{"id":123}'
                ]
            ]
        ];

        $this->sqsClient->sendMessageBatch($expectedPayload)->shouldBeCalled();

        $queue = new MessageQueue('https://queue-url.aws.com', $this->sqsClient->reveal());
        $queue->push(new DelayedMessage('message-name', ['id' => 123], 30));
        $queue->flush();
    }

    public function testCanPushMoreThanTenMessages()
    {
        $this->sqsClient->sendMessageBatch(Argument::any())->shouldBeCalledTimes(2);

        $queue = new MessageQueue('https://queue-url.aws.com', $this->sqsClient->reveal());

        for ($i = 0 ; $i != 15 ; ++$i) {
            $queue->push(new Message('message-name', ['id' => $i]), ['delay_seconds' => 30]);
        }

        $queue->flush();
    }

    public function testFlushClearMessages()
    {
        $this->sqsClient->sendMessageBatch(Argument::any())->shouldBeCalledTimes(1);

        $queue = new MessageQueue('https://queue-url.aws.com', $this->sqsClient->reveal());
        $queue->push(new Message('message-name', ['id' => 1]));

        $queue->flush();
        $queue->flush();
    }
}
