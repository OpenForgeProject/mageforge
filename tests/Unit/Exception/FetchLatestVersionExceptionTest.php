<?php

declare(strict_types=1);

namespace OpenForgeProject\MageForge\Test\Unit\Exception;

use OpenForgeProject\MageForge\Exception\FetchLatestVersionException;
use PHPUnit\Framework\TestCase;

class FetchLatestVersionExceptionTest extends TestCase
{
    public function testIsRuntimeException(): void
    {
        $exception = new FetchLatestVersionException('fetch failed');

        $this->assertInstanceOf(\RuntimeException::class, $exception);
        $this->assertSame('fetch failed', $exception->getMessage());
    }
}
