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
use Zend\Stratigility\MiddlewarePipe;

/**
 * Worker middleware
 * This class is kept for back compatibility reasons only
 * All it does is calling a new pipeline with WorkerMessageAttributesMiddleware and MessageRouterMiddleware
 *
 * @author Benoît Osterberger
 */
class WorkerMiddleware implements MiddlewareInterface
{
    const MESSAGE_ID_ATTRIBUTE           = 'worker.message_id';
    const MESSAGE_NAME_ATTRIBUTE         = 'worker.message_name';
    const MESSAGE_SCHEDULED_AT_ATTRIBUTE = 'worker.message_scheduled_at';
    const MESSAGE_PAYLOAD_ATTRIBUTE      = 'worker.message_payload';
    const MATCHED_QUEUE_ATTRIBUTE        = 'worker.matched_queue';
    const LOCALHOST_ADDRESSES            = ['127.0.0.1', '::1'];

    /**
     * @var ContainerInterface
     */
    private $container;

    /**
     * @param array $messagesMapping        kept only for BC
     * @param ContainerInterface $container
     */
    public function __construct(array $messagesMapping, ContainerInterface $container)
    {
        $this->container = $container;
    }

    /**
     * @param ServerRequestInterface $request
     * @param DelegateInterface      $delegate
     *
     * @return ResponseInterface
     */
    public function process(ServerRequestInterface $request, DelegateInterface $delegate): ResponseInterface
    {
        $app = new MiddlewarePipe();

        $app->pipe($this->container->get(WorkerMessageAttributesMiddleware::class));
        $app->pipe($this->container->get(MessageRouterMiddleware::class));

        $response = $app->process($request, $delegate);

        return $response;
    }
}
