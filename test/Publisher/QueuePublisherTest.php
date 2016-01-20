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

namespace ZfrEbWorkerTest\Publisher;

use Aws\Sqs\SqsClient;
use Prophecy\Argument;
use ZfrEbWorker\Exception\UnknownQueueException;
use ZfrEbWorker\Publisher\QueuePublisher;

class QueuePublisherTest extends \PHPUnit_Framework_TestCase
{
    /**
     * @var \Prophecy\Prophecy\ObjectProphecy
     */
    private $sqsClient;

    public function setUp()
    {
        $this->sqsClient = $this->prophesize(SqsClient::class);
    }

    public function testThrowExceptionIfPushToUnknownQueue()
    {
        $this->setExpectedException(UnknownQueueException::class);

        $publisher = new QueuePublisher([], $this->sqsClient->reveal());
        $publisher->push('unknown-queue', 'message-name');
    }

    public function testCanPushToSingleQueue()
    {
        $expectedPayload = [
            'QueueUrl' => 'https://queue-url.aws.com',
            'Entries'  => [
                [
                    'Id'           => 0,
                    'DelaySeconds' => 30,
                    'MessageBody'  => json_encode([
                        'name'    => 'message-name',
                        'payload' => [
                            'id' => 123
                        ]
                    ]),
                ]
            ]
        ];

        $this->sqsClient->sendMessageBatch($expectedPayload)->shouldBeCalled();

        $publisher = new QueuePublisher(['default_queue' => 'https://queue-url.aws.com'], $this->sqsClient->reveal());
        $publisher->push('default_queue', 'message-name', ['id' => 123], ['delay_seconds' => 30]);
        $publisher->flush();
    }

    public function testCanPushToSingleQueueSetLazily()
    {
        $expectedPayload = [
            'QueueUrl' => 'https://queue-url.aws.com',
            'Entries'  => [
                [
                    'Id'           => 0,
                    'DelaySeconds' => 30,
                    'MessageBody'  => json_encode([
                        'name'    => 'message-name',
                        'payload' => [
                            'id' => 123
                        ]
                    ]),
                ]
            ]
        ];

        $this->sqsClient->sendMessageBatch($expectedPayload);

        $publisher = new QueuePublisher([], $this->sqsClient->reveal());
        $publisher->setQueue('default_queue', 'https://queue-url.aws.com');
        $publisher->push('default_queue', 'message-name', ['id' => 123], ['delay_seconds' => 30]);
        $publisher->flush();
    }

    public function testCanPushMoreThanTenMessages()
    {
        $this->sqsClient->sendMessageBatch(Argument::any())->shouldBeCalledTimes(2);

        $publisher = new QueuePublisher(['default_queue' => 'https://queue-url.aws.com'], $this->sqsClient->reveal());

        for ($i = 0 ; $i != 15 ; ++$i) {
            $publisher->push('default_queue', 'message-name', ['id' => $i], ['delay_seconds' => 30]);
        }

        $publisher->flush();
    }

    public function testFlushClearMessages()
    {
        $this->sqsClient->sendMessageBatch(Argument::any())->shouldBeCalled();

        $publisher = new QueuePublisher(['default_queue' => 'https://queue-url.aws.com'], $this->sqsClient->reveal());
        $publisher->push('default_queue', 'message-name', ['id' => 1]);

        $publisher->flush();
        $publisher->flush();
    }

    public function testCanPushToMultipleQueues()
    {
        $firstExpectedPayload = [
            'QueueUrl' => 'https://first-queue-url.aws.com',
            'Entries'  => [
                [
                    'Id'           => 0,
                    'MessageBody'  => json_encode([
                        'name'    => 'message-name',
                        'payload' => []
                    ])
                ]
            ]
        ];

        $secondExpectedPayload = [
            'QueueUrl' => 'https://second-queue-url.aws.com',
            'Entries'  => [
                [
                    'Id'           => 0,
                    'MessageBody'  => json_encode([
                        'name'    => 'message-name',
                        'payload' => []
                    ])
                ]
            ]
        ];

        $this->sqsClient->sendMessageBatch($firstExpectedPayload);
        $this->sqsClient->sendMessageBatch($secondExpectedPayload);

        $queueConfig = [
            'first_queue'  => 'https://first-queue-url.aws.com',
            'second_queue' => 'https://second-queue-url.aws.com'
        ];

        $publisher = new QueuePublisher($queueConfig, $this->sqsClient->reveal());

        $publisher->push('first_queue', 'message-name');
        $publisher->push('second_queue', 'message-name');
        $publisher->flush();
    }
}