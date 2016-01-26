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
        // The full message is set as part of the body
        $body    = json_decode($request->getBody(), true);
        $name    = $body['name'];
        $payload = $body['payload'];

        // Let's create a middleware pipeline of mapped middlewares
        $pipeline = $this->createMiddlewarePipeline($name, $out);

        // Elastic Beanstalk set several headers. We will extract some of them and add them as part of the request
        // attributes so they can be easier to process, and set the message attributes
        $request = $request->withAttribute(self::MATCHED_QUEUE_ATTRIBUTE, $request->getHeaderLine('X-Aws-Sqsd-Queue'))
            ->withAttribute(self::MESSAGE_ID_ATTRIBUTE, $request->getHeaderLine('X-Aws-Sqsd-Msgid'))
            ->withAttribute(self::MESSAGE_PAYLOAD_ATTRIBUTE, $payload)
            ->withAttribute(self::MESSAGE_NAME_ATTRIBUTE, $name);

        return $pipeline($request, $response);
    }

    /**
     * @param string        $messageName
     * @param callable|null $out
     *
     * @return callable
     */
    private function createMiddlewarePipeline(string $messageName, callable $out = null): callable
    {
        if (!isset($this->messagesMapping[$messageName])) {
            throw new RuntimeException(sprintf(
                'No middleware was mapped for message "%s". Did you fill the "zfr_eb_worker" configuration?',
                $messageName
            ));
        }

        $mappedMiddlewares = $this->messagesMapping[$messageName];

        if (is_string($mappedMiddlewares)) {
            $mappedMiddlewares = (array) $mappedMiddlewares;
        }

        if (!is_array($mappedMiddlewares)) {
            throw new InvalidArgumentException(sprintf(
                'Mapped middleware must be either a string or an array of strings, %s given.',
                is_object($mappedMiddlewares) ? get_class($mappedMiddlewares) : gettype($mappedMiddlewares)
            ));
        }

        $pipeline = function (
            ServerRequestInterface $request,
            ResponseInterface $response
        ) use (
            &$pipeline,
            &$mappedMiddlewares,
            $out
        ) {
            if (empty($mappedMiddlewares)) {
                return is_callable($out) ? $out($request, $response) : $response;
            }

            /** @var callable $middleware */
            $middleware = $this->container->get(array_shift($mappedMiddlewares));

            return $middleware($request, $response, $pipeline);
        };

        return $pipeline;
    }
}
