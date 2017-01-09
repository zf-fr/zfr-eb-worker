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

use Interop\Container\ContainerInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use ZfrEbWorker\Exception\InvalidArgumentException;
use ZfrEbWorker\Exception\RuntimeException;

/**
 * Worker middleware
 * What this thing does is extracting the message from the request, and dispatching a pipeline of the mapped
 * middlewares. You can find a complete reference of what Elastic Beanstalk set here:
 * http://docs.aws.amazon.com/elasticbeanstalk/latest/dg/using-features-managing-env-tiers.html
 *
 * @author Michaël Gallego
 */
class WorkerMiddleware
{
    const MESSAGE_ID_ATTRIBUTE      = 'worker.message_id';
    const MESSAGE_NAME_ATTRIBUTE    = 'worker.message_name';
    const MESSAGE_PAYLOAD_ATTRIBUTE = 'worker.message_payload';
    const MATCHED_QUEUE_ATTRIBUTE   = 'worker.matched_queue';

    /**
     * @var ContainerInterface
     */
    private $container;

    /**
     * Map message names to a list middleware names. For instance:
     * [
     *     'image.saved' => [
     *         WorkerAuthenticationMiddleware::class,
     *         ProcessImageMiddleware::class,
     *     ],
     * ]
     *
     * @var array
     */
    private $messagesMapping;

    /**
     * @param array              $messagesMapping
     * @param ContainerInterface $container
     */
    public function __construct(array $messagesMapping, ContainerInterface $container)
    {
        $this->messagesMapping = $messagesMapping;
        $this->container       = $container;
    }

    /**
     * @param ServerRequestInterface $request
     * @param ResponseInterface      $response
     * @param callable|null          $out
     *
     * @return ResponseInterface
     */
    public function __invoke(
        ServerRequestInterface $request,
        ResponseInterface $response,
        callable $out = null
    ): ResponseInterface {
        // Two types of messages can be dispatched: either a periodic task or a normal task. For periodic tasks,
        // the worker daemon automatically adds the "X-Aws-Sqsd-Taskname" header. When we find it, we simply use this
        // name as the message name and continue the process

        if ($request->hasHeader('X-Aws-Sqsd-Taskname')) {
            // The full message is set as part of the body
            $name    = $request->getHeaderLine('X-Aws-Sqsd-Taskname');
            $payload = [];
        } else {
            // The full message is set as part of the body
            $name    = $request->getHeaderLine('X-Aws-Sqsd-Attr-name');
            $payload = json_decode($request->getBody(), true);
        }

        // Let's create a middleware pipeline of mapped middlewares
        $pipeline = new Pipeline($this->container, $this->getMiddlewaresForMessage($name), $out);

        // Elastic Beanstalk set several headers. We will extract some of them and add them as part of the request
        // attributes so they can be easier to process, and set the message attributes
        $request = $request->withAttribute(self::MATCHED_QUEUE_ATTRIBUTE, $request->getHeaderLine('X-Aws-Sqsd-Queue'))
            ->withAttribute(self::MESSAGE_ID_ATTRIBUTE, $request->getHeaderLine('X-Aws-Sqsd-Msgid'))
            ->withAttribute(self::MESSAGE_PAYLOAD_ATTRIBUTE, $payload)
            ->withAttribute(self::MESSAGE_NAME_ATTRIBUTE, $name);

        /** @var ResponseInterface $response */
        $response = $pipeline($request, $response);

        return $response->withHeader('X-Handled-By', 'ZfrEbWorker');
    }

    /**
     * @param string $messageName
     *
     * @return callable[]
     * @throws RuntimeException
     * @throws InvalidArgumentException
     */
    private function getMiddlewaresForMessage(string $messageName): array
    {
        if (!array_key_exists($messageName, $this->messagesMapping)) {
            throw new RuntimeException(sprintf(
                'No middleware was mapped for message "%s". Did you fill the "zfr_eb_worker" configuration?',
                $messageName
            ));
        }

        $mappedMiddlewares = $this->messagesMapping[$messageName];

        if (is_string($mappedMiddlewares)) {
            $mappedMiddlewares = [$mappedMiddlewares];
        }

        if (!is_array($mappedMiddlewares)) {
            throw new InvalidArgumentException(sprintf(
                'Mapped middleware must be either a string or an array of strings, %s given.',
                is_object($mappedMiddlewares) ? get_class($mappedMiddlewares) : gettype($mappedMiddlewares)
            ));
        }

        return $mappedMiddlewares;
    }
}
