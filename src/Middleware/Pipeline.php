<?php

namespace ZfrEbWorker\Middleware;

use Interop\Container\ContainerInterface;
use Interop\Http\ServerMiddleware\DelegateInterface;
use Interop\Http\ServerMiddleware\MiddlewareInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;

/**
 * @author Daniel Gimenes
 */
final class Pipeline
{
    /**
     * @var ContainerInterface
     */
    private $container;

    /**
     * @var callable[]
     */
    private $middlewares = [];

    /**
     * @param ContainerInterface $container
     * @param callable[]         $middlewares
     */
    public function __construct(ContainerInterface $container, array $middlewares)
    {
        $this->container   = $container;
        $this->middlewares = $middlewares;
    }

    /**
     * @param ServerRequestInterface $request
     * @param DelegateInterface      $delegate
     *
     * @return ResponseInterface
     */
    public function __invoke(ServerRequestInterface $request, DelegateInterface $delegate): ResponseInterface
    {
        if (empty($this->middlewares)) {
            return $delegate->process($request);
        }

        /** @var MiddlewareInterface $middleware */
        $middleware = $this->container->get(array_shift($this->middlewares));

        foreach ($this->middlewares as $middleware) {

        }
        $response   = $middleware->process($request, $delegate);

        return $result instanceof ResponseInterface ? $result : $response;
    }
}
