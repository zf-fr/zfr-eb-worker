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

namespace ZfrSqsWorker\Publisher;

use Aws\Sqs\SqsClient;
use ZfrSqsWorker\Exception\UnknownQueueException;

/**
 * @author Michaël Gallego
 */
class QueuePublisher implements QueuePublisherInterface
{
    /**
     * @var array
     */
    private $messages = [];

    /**
     * @var array
     */
    private $queues;

    /**
     * @var SqsClient
     */
    private $sqsClient;

    /**
     * @param array     $queues
     * @param SqsClient $sqsClient
     */
    public function __construct(array $queues, SqsClient $sqsClient)
    {
        $this->queues    = $queues;
        $this->sqsClient = $sqsClient;
    }

    /**
     * {@inheritDoc}
     */
    public function push(string $queue, string $jobName, array $attributes = [], array $options = [])
    {
        if (!isset($this->queues[$queue])) {
            throw new UnknownQueueException(sprintf(
                'Queue "%s" is not mapped to an actual SQS queue URL. Did you make sure you have specified the
                 queue into the "zfr_sqs_queue" config?',
                $queue
            ));
        }

        // We filter the options to valid SQS options only
        $options = array_intersect_key($options, ['Message' => '', 'MessageAttributes' => '']);

        $this->messages[$queue] = [
            'options' => $options,
            'body'    => [
                'job_name'   => $jobName,
                'attributes' => $attributes
            ]
        ];
    }

    /**
     * {@inheritDoc}
     */
    public function flush()
    {
        // SQS does not support flushing in batch to different queues

        foreach ($this->queues as $queue) {
            if (!isset($this->messages[$queue])) {
                continue;
            }

            $this->flushQueue($queue);
        }
    }

    /**
     * Flush a single queue
     *
     * @param  string $queue
     * @return void
     */
    private function flushQueue(string $queue)
    {
        $messages = $this->messages[$queue];

        // SQS only supports batch of 10, so we need to splice like that

        while (!empty($messages)) {
            $messagesToPush = array_splice($messages, 0, 10);
            $this->pushToQueue($queue, $messagesToPush);
        }
    }

    /**
     * @param string $queue
     * @param array  $messages
     */
    private function pushToQueue(string $queue, array $messages)
    {
        $queueUrl = $this->queues[$queue];

        $parameters = [
            'QueueUrl' => $queueUrl,
            'Entries'  => []
        ];

        foreach ($messages as $key => $message) {
            $messageParameters = [
                'Id'           => $key, // Identifier of the message in the batch
                'MessageBody'  => json_encode($message['body']),
                'DelaySeconds' => $message['options']['delay_seconds'] ?? null
            ];

            $parameters['Entries'][] = array_filter($messageParameters, function ($value) {
                return $value !== null;
            });
        }

        $this->sqsClient->sendMessageBatch($parameters);
    }
}