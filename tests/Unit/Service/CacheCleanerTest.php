<?php

declare(strict_types=1);

namespace OpenForgeProject\MageForge\Test\Unit\Service;

use Magento\Framework\Shell;
use OpenForgeProject\MageForge\Service\CacheCleaner;
use PHPUnit\Framework\MockObject\Stub;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Console\Style\SymfonyStyle;

class CacheCleanerTest extends TestCase
{
    /**
     * @var Shell&Stub
     */
    private $shell;
    /**
     * @var SymfonyStyle&Stub
     */
    private $io;
    /**
     * @var CacheCleaner
     */
    private CacheCleaner $cacheCleaner;

    protected function setUp(): void
    {
        $this->shell = $this->createStub(Shell::class);
        $this->io = $this->createStub(SymfonyStyle::class);
        $this->cacheCleaner = new CacheCleaner($this->shell);
    }

    public function testCleansFrontendCacheTypes(): void
    {
        $shell = $this->createMock(Shell::class);
        $shell
            ->expects($this->once())
            ->method('execute')
            ->with('bin/magento cache:clean full_page block_html layout translate');

        $this->assertTrue((new CacheCleaner($shell))->clean($this->io, false));
    }

    public function testPrintsProgressInVerboseMode(): void
    {
        $io = $this->createMock(SymfonyStyle::class);
        $io->expects($this->once())->method('text')->with('Cleaning cache...');
        $io->expects($this->once())->method('success')->with('Cache cleaned successfully.');

        $this->assertTrue($this->cacheCleaner->clean($io, true));
    }

    public function testStaysQuietWhenNotVerbose(): void
    {
        $io = $this->createMock(SymfonyStyle::class);
        $io->expects($this->never())->method('text');
        $io->expects($this->never())->method('success');

        $this->assertTrue($this->cacheCleaner->clean($io, false));
    }

    public function testReturnsFalseAndPrintsErrorWhenShellFails(): void
    {
        $this->shell->method('execute')->willThrowException(new \RuntimeException('cache backend gone'));
        $io = $this->createMock(SymfonyStyle::class);
        $io->expects($this->once())->method('error')->with('Failed to clean cache: cache backend gone');

        $this->assertFalse($this->cacheCleaner->clean($io, true));
    }
}
