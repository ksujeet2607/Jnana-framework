<?php

namespace Jnana\Http;

/**
 * @mixin Response
 */
class RedirectResponse extends Response
{
    public function __construct(string $url, int $statusCode = 302)
    {
        $this->setStatusCode($statusCode);
        $this->addHeader('Location', $url);
    }

}
