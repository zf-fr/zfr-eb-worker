<?php

namespace ZfrEbWorker\Middleware;

use Interop\Container\ContainerInterface;
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
     * @var null|callable
     */
    private $out;

    /**
     * @param ContainerInterface $container
     * @param callable[]         $middlewares
     * @param null|callable      $out
     */
    public function __construct(ContainerInterface $container, array $middlewares, callable $out = null)
    {
        $this->container   = $container;
        $this->middlewares = $middlewares;
        $this->out         = $out;
    }

    /**
     * @param ServerRequestInterface $request
     * @param ResponseInterface      $response
     *
     * @return ResponseInterface
     */
    public function __invoke(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface
    {
        if (0 === count($this->middlewares)) {
            $out = $this->out;

            return null !== $out ? $out($request, $response) : $response;
        }

        /** @var callable $middleware */
        $middleware = $this->container->get(array_shift($this->middlewares));
        $result     = $middleware($request, $response, $this);

        return $result instanceof ResponseInterface ? $result : $response;
    }
}
