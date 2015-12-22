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

namespace ZfrEbWorker;

use Interop\Container\ContainerInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use ZfrEbWorker\Exception\RuntimeException;

/**
 * Worker middleware
 *
 * What this thing does is extracting the message from the request, and dispatching to the proper middleware. Because
 * Zend Expressive does not have a simple way of redirecting, the simplest way is simply to fetch the corresponding middleware,
 * and do the routing here.
 *
 * You can find a complete reference of what Elastic Beanstalk set here: http://docs.aws.amazon.com/elasticbeanstalk/latest/dg/using-features-managing-env-tiers.html
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
     * Map job names to a middleware. For instance:
     *
     * [
     *      'process_image' => ProcessImageMiddleware::class
     * ]
     *
     * @var array
     */
    private $jobMapping;

    /**
     * @param array              $jobMapping
     * @param ContainerInterface $container
     */
    public function __construct(array $jobMapping, ContainerInterface $container)
    {
        $this->jobMapping = $jobMapping;
        $this->container  = $container;
    }

    /**
     * @param ServerRequestInterface $request
     * @param ResponseInterface      $response
     * @param callable|null          $out
     */
    public function __invoke(ServerRequestInterface $request, ResponseInterface $response, callable $out = null)
    {
        // The full message is set as part of the body
        $body    = json_decode($request->getBody(), true)['body'];
        $jobName = $body['job_name'];
        $message = $body['attributes'];

        // Elastic Beanstalk set several headers. We will extract some of them and add them as part of the request attributes
        // so they can be easier to process, and set the message attributes
        $request = $request->withAttribute('worker.matched_queue', $request->getHeaderLine('X-Aws-Sqsd-Queue'))
            ->withAttribute('worker.message_id', $request->getHeaderLine('X-Aws-Sqsd-Msgid'))
            ->withAttribute('worker.message_body', $message);

        // Finally, let's retrieve the good middleware and dispatch to it
        $middleware = $this->getMiddlewareForJob($jobName);

        return $middleware($request, $response, $out);
    }

    /**
     * @param  string $jobName
     * @return callable
     */
    private function getMiddlewareForJob(string $jobName): callable
    {
        if (!isset($this->jobMapping[$jobName])) {
            throw new RuntimeException(sprintf(
                'No middleware could be found for job "%s". Did you have properly fill the "zfr_sqs_worker" configuration?',
                $jobName
            ));
        }

        return $this->container->get($jobName);
    }
}