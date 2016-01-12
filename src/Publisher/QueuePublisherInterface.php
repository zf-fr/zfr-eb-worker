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

/**
 * Interface for a queue publisher
 *
 * A queue publisher is a thin layer around AWS SDK for SQS. In order to push a new message,
 * you must provide a middleware name, arbitrary data and some options. Once everything is done,
 * you can flush. This is an optimized operation and the flush will make sure to create the minimum
 * number of messages to SQS.
 *
 * @author Michaël Gallego
 */
interface QueuePublisherInterface
{
    /**
     * Push a message into the queue
     *
     * Supported options for now are:
     *      - delay_seconds: number of seconds the message is delayed before being processed
     *
     * @param  string $queue
     * @param  string $taskName
     * @param  array  $attributes
     * @param  array  $options
     * @return void
     */
    public function push(string $queue, string $taskName, array $attributes = [], array $options = []);

    /**
     * Adjust the visibility of a given message
     *
     * @param  string $queue
     * @param  string $receiptHandle
     * @param  int    $visibility
     * @return void
     */
    public function changeMessageVisibility(string $queue, string $receiptHandle, int $visibility);

    /**
     * Flush the queue
     *
     * @return void
     */
    public function flush();
}