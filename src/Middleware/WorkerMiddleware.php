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
use ZfrEbWorker\Exception\RuntimeException;

/**
 * Worker middleware
 *
 * What this thing does is extracting the message from the request, and dispatching to the proper middleware. Because
 * Zend Expressive does not have a simple way of redirecting, the simplest way is simply to fetch the corresponding
 * middleware, and do the routing here.
 *
 * You can find a complete reference of what Elastic Beanstalk set here:
 * http://docs.aws.amazon.com/elasticbeanstalk/latest/dg/using-features-managing-env-tiers.html
 *
 * @author Michaël Gallego
 */
class WorkerMiddleware
{
    /**
     * @var ContainerInterface
     */
    private $container;

    /**
     * Map task names to a middleware. For instance:
     *
     * [
     *      'process_image' => ProcessImageMiddleware::class
     * ]
     *
     * @var array
     */
    private $taskMapping;

    /**
     * @param array              $taskMapping
     * @param ContainerInterface $container
     */
    public function __construct(array $taskMapping, ContainerInterface $container)
    {
        $this->taskMapping = $taskMapping;
        $this->container   = $container;
    }

    /**
     * @param ServerRequestInterface $request
     * @param ResponseInterface      $response
     * @param callable|null          $out
     */
    public function __invoke(ServerRequestInterface $request, ResponseInterface $response, callable $out = null)
    {
        // The full message is set as part of the body
        $body     = json_decode($request->getBody(), true);
        $taskName = $body['task_name'];
        $message  = $body['attributes'];

        // Let's retrieve the correct middleware by using the mapping
        $middleware = $this->getMiddlewareForTask($taskName);

        // Elastic Beanstalk set several headers. We will extract some of them and add them as part of the request
        // attributes so they can be easier to process, and set the message attributes
        $request = $request->withAttribute('worker.matched_queue', $request->getHeaderLine('X-Aws-Sqsd-Queue'))
            ->withAttribute('worker.message_id', $request->getHeaderLine('X-Aws-Sqsd-Msgid'))
            ->withAttribute('worker.message_body', $message)
            ->withAttribute('worker.task_name', $taskName);

        return $middleware($request, $response, $out);
    }

    /**
     * @param  string $taskName
     * @return callable
     */
    private function getMiddlewareForTask(string $taskName): callable
    {
        if (!isset($this->taskMapping[$taskName])) {
            throw new RuntimeException(sprintf(
                'No middleware could be found for task "%s". Did you have properly fill
                the "zfr_eb_worker" configuration?',
                $taskName
            ));
        }

        return $this->container->get($this->taskMapping[$taskName]);
    }
}
