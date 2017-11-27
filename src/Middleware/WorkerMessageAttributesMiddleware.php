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

use Psr\Container\ContainerInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Webimpress\HttpMiddlewareCompatibility\HandlerInterface as DelegateInterface;
use Webimpress\HttpMiddlewareCompatibility\MiddlewareInterface;
use ZfrEbWorker\Exception\InvalidArgumentException;
use ZfrEbWorker\Exception\RuntimeException;

/**
 * WorkerMessageAttributes middleware
 * What this thing does is validating worker request and extracts message attributes
 * You can find a complete reference of what Elastic Beanstalk set here:
 * http://docs.aws.amazon.com/elasticbeanstalk/latest/dg/using-features-managing-env-tiers.html
 *
 * @author Michaël Gallego
 */
class WorkerMessageAttributesMiddleware implements MiddlewareInterface
{
    const MESSAGE_ID_ATTRIBUTE           = 'worker.message_id';
    const MESSAGE_NAME_ATTRIBUTE         = 'worker.message_name';
    const MESSAGE_SCHEDULED_AT_ATTRIBUTE = 'worker.message_scheduled_at';
    const MESSAGE_PAYLOAD_ATTRIBUTE      = 'worker.message_payload';
    const MATCHED_QUEUE_ATTRIBUTE        = 'worker.matched_queue';
    const LOCALHOST_ADDRESSES            = ['127.0.0.1', '::1'];

    /**
     * @param ServerRequestInterface $request
     * @param DelegateInterface      $delegate
     *
     * @return ResponseInterface
     */
    public function process(ServerRequestInterface $request, DelegateInterface $delegate): ResponseInterface
    {
        $this->assertLocalhost($request);
        $this->assertSqsUserAgent($request);

        // Two types of messages can be dispatched: either a periodic task or a normal task. For periodic tasks,
        // the worker daemon automatically adds the "X-Aws-Sqsd-Taskname" header. When we find it, we simply use this
        // name as the message name and continue the process

        if ($request->hasHeader('X-Aws-Sqsd-Taskname')) {
            // The full message is set as part of the body
            $name    = $request->getHeaderLine('X-Aws-Sqsd-Taskname');
            $payload = [];
        } else {
            // The full message is set as part of the body
            $name    = $request->getHeaderLine('X-Aws-Sqsd-Attr-Name');
            $payload = json_decode($request->getBody(), true);
        }

        // Elastic Beanstalk set several headers. We will extract some of them and add them as part of the request
        // attributes so they can be easier to process, and set the message attributes
        $request = $request->withAttribute(self::MATCHED_QUEUE_ATTRIBUTE, $request->getHeaderLine('X-Aws-Sqsd-Queue'))
            ->withAttribute(self::MESSAGE_ID_ATTRIBUTE, $request->getHeaderLine('X-Aws-Sqsd-Msgid'))
            ->withAttribute(self::MESSAGE_SCHEDULED_AT_ATTRIBUTE, $request->getHeaderLine('X-Aws-Sqsd-Scheduled-At'))
            ->withAttribute(self::MESSAGE_PAYLOAD_ATTRIBUTE, $payload)
            ->withAttribute(self::MESSAGE_NAME_ATTRIBUTE, $name)
            ->withParsedBody($payload);

        $response = $delegate->process($request, $delegate);

        // Some middleware may return a 204 or any other 2xx answer, which are considered as success. However Elastic Beanstalk is picky
        // and only accept 200 OK to delete message. So we normalize any status in the 2xx range
        $statusCode = $response->getStatusCode();

        if ($statusCode >= 200 && $statusCode <= 299) {
            $response = $response->withStatus(200);
        }

        return $response->withHeader('X-Handled-By', 'ZfrEbWorker');
    }

    /**
     * @param ServerRequestInterface $request
     */
    private function assertLocalhost(ServerRequestInterface $request)
    {
        $serverParams = $request->getServerParams();
        $remoteAddr   = $serverParams['REMOTE_ADDR'] ?? 'unknown IP address';

        // If request is not originating from localhost or from Docker local IP, we throw an RuntimeException
        if (!in_array($remoteAddr, self::LOCALHOST_ADDRESSES) && !fnmatch('172.*', $remoteAddr)) {
            throw new RuntimeException(sprintf(
                'Worker requests must come from localhost, request originated from %s given',
                $remoteAddr
            ));
        }
    }

    /**
     * @param ServerRequestInterface $request
     */
    private function assertSqsUserAgent(ServerRequestInterface $request)
    {
        $userAgent = $request->getHeaderLine('User-Agent');

        if (false === stripos($userAgent, 'aws-sqsd')) {
            throw new RuntimeException('Worker requests must come from "aws-sqsd" user agent');
        }
    }
}
