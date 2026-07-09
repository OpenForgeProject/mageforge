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
    /**
     * @var string
     */
    private string $lastUrl = '';

    /**
     * @param array<string,array{int,string}> $responsesByUrl
     */
    public function __construct(
        private readonly array $responsesByUrl = [],
    ) {
    }

    /**
     * Records the requested URI.
     *
     * ClientInterface::get() is declared `@return array`, so PHPStan requires a return value
     * here — even though the real clients (e.g. Curl) return void and the response is read via
     * getStatus()/getBody(). An empty array is a neutral stub; the tests never use it.
     *
     * @param string $uri
     * @return array<mixed>
     */
    public function get($uri)
    {
        $this->lastUrl = (string) $uri;

        return [];
    }

    /**
     * Returns the preconfigured status code for the last requested URI.
     */
    public function getStatus()
    {
        return $this->responsesByUrl[$this->lastUrl][0] ?? 404;
    }

    /**
     * Returns the preconfigured body for the last requested URI.
     */
    public function getBody()
    {
        return $this->responsesByUrl[$this->lastUrl][1] ?? '';
    }

    /**
     * Records the requested URI; the payload is ignored.
     *
     * @param string $uri
     * @param array<mixed>|string $params
     */
    public function post($uri, $params)
    {
        $this->lastUrl = (string) $uri;
    }

    /**
     * Returns no response headers.
     *
     * @return array<mixed>
     */
    public function getHeaders()
    {
        return [];
    }

    /**
     * Returns no response cookies.
     *
     * @return array<mixed>
     */
    public function getCookies()
    {
        return [];
    }

    /**
     * No-op stub for the request timeout.
     *
     * @param int $value
     */
    public function setTimeout($value)
    {
    }

    /**
     * No-op stub for the request headers.
     *
     * @param array<mixed> $headers
     */
    public function setHeaders($headers)
    {
    }

    /**
     * No-op stub for adding a request header.
     *
     * @param string $name
     * @param string $value
     */
    public function addHeader($name, $value)
    {
    }

    /**
     * No-op stub for removing a request header.
     *
     * @param string $name
     */
    public function removeHeader($name)
    {
    }

    /**
     * No-op stub for basic-auth credentials.
     *
     * @param string $login
     * @param string $pass
     */
    public function setCredentials($login, $pass)
    {
    }

    /**
     * No-op stub for adding a request cookie.
     *
     * @param string $name
     * @param string $value
     */
    public function addCookie($name, $value)
    {
    }

    /**
     * No-op stub for the request cookies.
     *
     * @param array<mixed> $cookies
     */
    public function setCookies($cookies)
    {
    }

    /**
     * No-op stub for removing a request cookie.
     *
     * @param string $name
     */
    public function removeCookie($name)
    {
    }

    /**
     * No-op stub for removing all request cookies.
     */
    public function removeCookies()
    {
    }

    /**
     * No-op stub for a single cURL option.
     *
     * @param string $name
     * @param string $value
     */
    public function setOption($name, $value)
    {
    }

    /**
     * No-op stub for multiple cURL options.
     *
     * @param array<mixed> $arr
     */
    public function setOptions($arr)
    {
    }
}
