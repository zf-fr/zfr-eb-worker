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

namespace ZfrEbWorker\Queue;

use Aws\Sqs\SqsClient;
use ZfrEbWorker\Exception\RuntimeException;
use ZfrEbWorker\Message\MessageInterface;

/**
 * @author Michaël Gallego
 */
class Queue implements QueueInterface
{
    /**
     * @var array[]
     */
    private $messages = [];

    /**
     * @var SqsClient
     */
    private $sqsClient;

    /**
     * @var string
     */
    private $name;

    /**
     * @var string
     */
    private $url;

    /**
     * @param SqsClient $sqsClient
     * @param string    $name
     * @param string    $url
     */
    public function __construct(SqsClient $sqsClient, string $name, string $url = '')
    {
        $this->sqsClient = $sqsClient;
        $this->name      = $name;
        $this->url       = $url;
    }

    /**
     * {@inheritDoc}
     */
    public function setQueueUrl(string $url)
    {
        $this->url = $url;
    }

    /**
     * {@inheritDoc}
     */
    public function push(MessageInterface $message, array $options = [])
    {
        $this->messages[] = [
            'options' => $options,
            'body'    => [
                'name'    => $message->getName(),
                'payload' => $message->getPayload()
            ]
        ];
    }

    /**
     * {@inheritDoc}
     */
    public function flush(bool $async = false)
    {
        if (null === $this->url) {
            throw new RuntimeException(sprintf(
                'Queue "%s" is not mapped to an actual SQS queue URL. Did you make sure you have specified the
                 queue into the "zfr_eb_worker" config?',
                $this->name
            ));
        }

        $this->doFlush($async);

        // We reset the messages so that we make sure we don't duplicate message by calling flush multiple times
        $this->messages = [];
    }

    /**
     * Flush a queue
     *
     * @param  bool $async
     * @return void
     */
    private function doFlush(bool $async)
    {
        // SQS only supports batch of 10, so we need to splice like that

        while (!empty($this->messages)) {
            $messagesToPush = array_splice($this->messages, 0, 10);

            $parameters = [
                'QueueUrl' => $this->url,
                'Entries'  => []
            ];

            foreach ($messagesToPush as $key => $message) {
                $messageParameters = [
                    'Id'           => $key, // Identifier of the message in the batch
                    'MessageBody'  => json_encode($message['body']),
                    'DelaySeconds' => $message['options']['delay_seconds'] ?? null
                ];

                $parameters['Entries'][] = array_filter($messageParameters, function ($value) {
                    return $value !== null;
                });
            }

            if ($async) {
                $this->sqsClient->sendMessageBatchAsync($parameters);
            } else {
                $this->sqsClient->sendMessageBatch($parameters);
            }
        }
    }
}
