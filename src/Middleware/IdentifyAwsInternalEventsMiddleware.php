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

namespace ZfrEbWorker\Middleware;

use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Webimpress\HttpMiddlewareCompatibility\HandlerInterface as DelegateInterface;
use Webimpress\HttpMiddlewareCompatibility\MiddlewareInterface;

/**
 * AWS internal events detector middleware
 *
 * @author Gaultier Boniface
 */
class IdentifyAwsInternalEventsMiddleware implements MiddlewareInterface
{
    public function process(ServerRequestInterface $request, DelegateInterface $delegate): ResponseInterface
    {
        $body = json_decode($request->getBody()->getContents(), true);

        if (json_last_error() === JSON_ERROR_NONE) {
            if (array_key_exists('Records', $body)) { // if this key exists the event is probably from S3
                // `configurationId` is set by the user as the custom name of the event in S3 console, if not set we use a default based on event source and event name (example: `aws:s3:ObjectCreated:Put`)
                $eventName = $body['Records'][0]['s3']['configurationId'] ?? $body['Records'][0]['eventSource'] . ':' . $body['Records'][0]['eventName'];
            }

            // TODO: find a way to handle all other AWS internal events that could be sent to a SQS queue
        }

        $request = $request->withAttribute(WorkerMiddleware::MESSAGE_NAME_ATTRIBUTE, $eventName);

        return $delegate->process($request);
    }
}
