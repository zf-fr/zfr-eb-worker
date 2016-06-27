<?php

namespace ZfrEbWorker\Middleware;

use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;

/**
 * Middleware that protects the worker middleware by only allowing localhost requests
 *
 * @author Michaël Gallego
 */
class LocalhostCheckerMiddleware
{
    /**
     * @var array
     */
    private $localhost = ['127.0.0.1', '::1'];

    /**
     * @param ServerRequestInterface $request
     * @param ResponseInterface      $response
     * @param callable|null          $out
     * @return ResponseInterface
     */
    public function __invoke(
        ServerRequestInterface $request,
        ResponseInterface $response,
        callable $out = null
    ): ResponseInterface {
        $serverParams = $request->getServerParams();
        $remoteAddr   = $serverParams['REMOTE_ADDR'] ?? '';

        // If request is not originating from localhost, we simply return 200
        if (!in_array($remoteAddr, $this->localhost)) {
            return $response->withStatus(200);
        }

        return $out($request, $response, $out);
    }
}
