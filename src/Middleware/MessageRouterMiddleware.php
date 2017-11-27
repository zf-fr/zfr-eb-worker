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
 * MessageRouter middleware
 * What this thing does is Routing to a specific handler based on message name.
 * You can find a complete reference of what Elastic Beanstalk set here:
 * http://docs.aws.amazon.com/elasticbeanstalk/latest/dg/using-features-managing-env-tiers.html
 *
 * @author Michaël Gallego
 */
class MessageRouterMiddleware implements MiddlewareInterface
{
    /**
     * @var ContainerInterface
     */
    private $container;

    /**
     * Map message names to a middleware name. For instance:
     * [
     *     'image.saved' => ProcessImageMiddleware::class
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
     * @param DelegateInterface      $delegate
     *
     * @return ResponseInterface
     */
    public function process(ServerRequestInterface $request, DelegateInterface $delegate): ResponseInterface
    {
        $name = $request->getAttribute(WorkerMessageAttributesMiddleware::MESSAGE_NAME_ATTRIBUTE);

        // Let's create a middleware pipeline of mapped middlewares
        $middleware = $this->getMiddlewareForMessage($name);

        return $middleware->process($request, $delegate);
    }

    /**
     * @param string $messageName
     *
     * @return MiddlewareInterface
     * @throws RuntimeException
     * @throws InvalidArgumentException
     */
    private function getMiddlewareForMessage(string $messageName): MiddlewareInterface
    {
        if (!array_key_exists($messageName, $this->messagesMapping)) {
            throw new RuntimeException(sprintf(
                'No middleware was mapped for message "%s". Did you fill the "zfr_eb_worker" configuration?',
                $messageName
            ));
        }

        $mappedMiddleware = $this->messagesMapping[$messageName];

        if (!is_string($mappedMiddleware)) {
            throw new InvalidArgumentException(sprintf(
                'Mapped middleware must be a string, %s given.',
                is_object($mappedMiddleware) ? get_class($mappedMiddleware) : gettype($mappedMiddleware)
            ));
        }

        return $this->container->get($mappedMiddleware);
    }
}
