<?php

namespace Jnana\Middleware;

use Jnana\Http\Request;
use Jnana\Http\Response;
use Psr\Cache\CacheItemPoolInterface;
use Psr\Log\LoggerInterface;

class CacheSaveMiddleware implements MiddlewareInterface
{
    private CacheItemPoolInterface $cache;
    private LoggerInterface $logger;
    private int $cacheDuration;

    public function __construct(CacheItemPoolInterface $cache, LoggerInterface $logger, int $cacheDuration = 3600)
    {
        $this->cache = $cache;
        $this->logger = $logger;
        $this->cacheDuration = $cacheDuration; // Default cache duration 1 hour
    }

    /**
     * Process the request and handle caching logic.
     */
    public function process(Request $request, Response $response, callable $next): Response
    {
        // Cache the response after controller execution
        $cacheKey = $this->generateCacheKey($request);

        $cachedResponse = $this->cache->getItem($cacheKey);
        if (!$cachedResponse->isHit()) {
            $this->logger->info("Cache miss for {$cacheKey}");

            $this->cache->save(
                $cachedResponse
                    ->set([
                        'statusCode' => $response->getStatusCode(),
                        'headers' => $response->getHeaders(),
                        'body' => $response->getBody(),
                    ])
                    ->expiresAfter($this->cacheDuration)
            );
        }

        // Return the response
        return $response;
    }

    /**
     * Generate a cache key based on request parameters.
     */
    private function generateCacheKey(Request $request): string
    {
        return md5($request->getMethod() . $request->getFullUri());
    }

}
