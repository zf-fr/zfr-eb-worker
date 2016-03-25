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

namespace ZfrEbWorker\MessageQueue;

use Aws\Sqs\SqsClient;
use ZfrEbWorker\Message\DelayedMessage;
use ZfrEbWorker\Message\MessageInterface;

/**
 * @author Michaël Gallego
 */
class MessageQueue implements MessageQueueInterface
{
    /**
     * Default flags for json_encode; value of:
     *
     * <code>
     * JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_AMP | JSON_HEX_QUOT | JSON_UNESCAPED_SLASHES
     * </code>
     *
     * @const int
     */
    const DEFAULT_JSON_FLAGS = 79;

    /**
     * @var array[]
     */
    private $messages = [];

    /**
     * @var string
     */
    private $url;

    /**
     * @var SqsClient
     */
    private $sqsClient;

    /**
     * @param string    $url
     * @param SqsClient $sqsClient
     */
    public function __construct(string $url, SqsClient $sqsClient)
    {
        $this->url       = $url;
        $this->sqsClient = $sqsClient;
    }

    /**
     * {@inheritDoc}
     */
    public function push(MessageInterface $message)
    {
        $this->messages[] = [
            'options' => [
                'delay_seconds' => ($message instanceof DelayedMessage) ? $message->getDelay() : 0
            ],
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
        $this->doFlush($async);
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
                    'MessageBody'  => json_encode($message['body'], self::DEFAULT_JSON_FLAGS),
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
