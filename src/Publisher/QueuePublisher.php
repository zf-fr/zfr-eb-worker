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

namespace ZfrEbWorker\Publisher;

use Aws\Sqs\SqsClient;
use ZfrEbWorker\Exception\UnknownQueueException;

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
    public function setQueue(string $queue, string $queueUrl)
    {
        $this->queues[$queue] = $queueUrl;
    }

    /**
     * {@inheritDoc}
     */
    public function push(string $queue, string $taskName, array $attributes = [], array $options = [])
    {
        if (!isset($this->queues[$queue])) {
            throw new UnknownQueueException(sprintf(
                'Queue "%s" is not mapped to an actual SQS queue URL. Did you make sure you have specified the
                 queue into the "zfr_eb_worker" config?',
                $queue
            ));
        }

        $queueUrl = $this->queues[$queue];

        $this->messages[$queueUrl][] = [
            'options' => $options,
            'body'    => [
                'task_name'  => $taskName,
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

        foreach ($this->messages as $queueUrl => $messagesByQueue) {
            $this->flushQueue($queueUrl);
        }

        // We reset the messages so that we make sure we don't duplicate message by calling flush multiple times
        $this->messages = [];
    }

    /**
     * Flush a queue
     *
     * @param  string $queueUrl
     * @return void
     */
    private function flushQueue(string $queueUrl)
    {
        $messages = $this->messages[$queueUrl];

        // SQS only supports batch of 10, so we need to splice like that

        while (!empty($messages)) {
            $messagesToPush = array_splice($messages, 0, 10);
            $this->pushToQueue($queueUrl, $messagesToPush);
        }
    }

    /**
     * @param string $queueUrl
     * @param array  $messages
     */
    private function pushToQueue(string $queueUrl, array $messages)
    {
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
