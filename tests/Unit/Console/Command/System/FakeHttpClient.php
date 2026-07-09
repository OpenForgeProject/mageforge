<?php

declare(strict_types=1);

namespace OpenForgeProject\MageForge\Test\Unit\Console\Command\System;

use Magento\Framework\HTTP\ClientInterface;

/**
 * Deterministic HTTP client double: returns a preconfigured [status, body]
 * per URL and 404/'' for everything else.
 */
class FakeHttpClient implements ClientInterface
{
    private string $lastUrl = '';

    /**
     * @param array<string, array{int, string}> $responsesByUrl
     */
    public function __construct(
        private readonly array $responsesByUrl = [],
    ) {
    }

    /**
     * @return array<mixed>
     */
    public function get($uri)
    {
        $this->lastUrl = (string) $uri;

        return [];
    }

    public function getStatus()
    {
        return $this->responsesByUrl[$this->lastUrl][0] ?? 404;
    }

    public function getBody()
    {
        return $this->responsesByUrl[$this->lastUrl][1] ?? '';
    }

    /**
     * @param array<mixed>|string $params
     */
    public function post($uri, $params)
    {
        $this->lastUrl = (string) $uri;
    }

    /**
     * @return array<mixed>
     */
    public function getHeaders()
    {
        return [];
    }

    /**
     * @return array<mixed>
     */
    public function getCookies()
    {
        return [];
    }

    public function setTimeout($value)
    {
    }

    /**
     * @param array<mixed> $headers
     */
    public function setHeaders($headers)
    {
    }

    public function addHeader($name, $value)
    {
    }

    public function removeHeader($name)
    {
    }

    public function setCredentials($login, $pass)
    {
    }

    public function setCookie(string $name, string $value): void
    {
    }

    public function addCookie($name, $value)
    {
    }

    /**
     * @param array<mixed> $cookies
     */
    public function setCookies($cookies)
    {
    }

    public function removeCookie($name)
    {
    }

    public function removeCookies()
    {
    }

    public function setOption($name, $value)
    {
    }

    /**
     * @param array<mixed> $arr
     */
    public function setOptions($arr)
    {
    }
}
