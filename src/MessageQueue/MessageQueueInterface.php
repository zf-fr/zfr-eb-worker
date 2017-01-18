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

use ZfrEbWorker\Message\MessageInterface;

/**
 * Interface for a queue
 *
 * The queue is a thin layer around AWS SDK for SQS. In order to push a new message,
 * you must provide a middleware name, arbitrary data and some options. Once everything is done,
 * you can flush. This is an optimized operation and the flush will make sure to create the minimum
 * number of messages to SQS.
 *
 * @author Michaël Gallego
 */
interface MessageQueueInterface
{
    /**
     * Push a message into the queue
     *
     * @param  MessageInterface $message
     * @return void
     */
    public function push(MessageInterface $message);

    /**
     * Flush the queue
     *
     * @param  bool $async
     * @return void
     */
    public function flush(bool $async = false);
}
