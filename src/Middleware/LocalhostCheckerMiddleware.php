<?php

namespace ZfrEbWorker\Middleware;

use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use ZfrEbWorker\Exception\RuntimeException;

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
        $remoteAddr   = $serverParams['REMOTE_ADDR'] ?? 'unknown IP address';

        // If request is not originating from localhost or from Docker local IP, we throw an RuntimeException
        if (!in_array($remoteAddr, $this->localhost) && !fnmatch('172.17.*', $remoteAddr)) {
            throw new RuntimeException(sprintf(
                'Worker requests must come from localhost, request originated from %s given',
                $remoteAddr
            ));
        }

        return $out($request, $response, $out);
    }
}
