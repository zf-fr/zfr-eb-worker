<?php

namespace ZfrEbWorker\Listener;

use Psr\Http\Message\ServerRequestInterface;
use Webimpress\HttpMiddlewareCompatibility\HandlerInterface as DelegateInterface;
use Webimpress\HttpMiddlewareCompatibility\MiddlewareInterface;
use Zend\Diactoros\Response\EmptyResponse;

/**
 * @author Daniel Gimenes
 */
class SilentFailingListener implements MiddlewareInterface
{
    /**
     * {@inheritDoc}
     */
    public function process(ServerRequestInterface $request, DelegateInterface $delegate)
    {
        return new EmptyResponse(200);
    }
}
