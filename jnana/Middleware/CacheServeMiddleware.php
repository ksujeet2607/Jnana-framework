<?php

namespace Jnana\Middleware;

use Jnana\Http\Request;
use Jnana\Http\Response;
use Psr\Cache\CacheItemPoolInterface;
use Psr\Log\LoggerInterface;

class CacheServeMiddleware implements MiddlewareInterface
{
    private CacheItemPoolInterface $cache;
    private LoggerInterface $logger;

    public function __construct(CacheItemPoolInterface $cache, LoggerInterface $logger)
    {
        $this->cache = $cache;
        $this->logger = $logger;
    }

    public function process(Request $request, Response $response, callable $next): Response
    {
        $cacheKey = $this->generateCacheKey($request);
        $cachedResponse = $this->cache->getItem($cacheKey);

        if ($cachedResponse->isHit()) {
            $this->logger->info("Cache hit for {$cacheKey}");
            $cachedData = $cachedResponse->get();
            $response->setCacheHit(true); // Set the custom flag to true
            return $this->rebuildResponseFromCache($response, $cachedData);
        }

        // Proceed to the next middleware/controller if cache miss
        return $next($request, $response);
    }

    private function generateCacheKey(Request $request): string
    {
        return md5($request->getMethod() . $request->getFullUri());
    }

    private function rebuildResponseFromCache(Response $response, array $cachedData): Response
    {
        $response->setStatusCode($cachedData['statusCode'] ?? 200);

        foreach ($cachedData['headers'] ?? [] as $key => $value) {
            $response->addHeader($key, $value);
        }
        $response->addHeader('X-Cache-Status', 'HIT');

        $response->setBody($cachedData['body'] ?? '');
        return $response;
    }
}
