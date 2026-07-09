<?php

declare(strict_types=1);

namespace OpenForgeProject\MageForge\Test\Unit\Service;

use Magento\Framework\Shell;
use OpenForgeProject\MageForge\Service\CacheCleaner;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Console\Style\SymfonyStyle;

class CacheCleanerTest extends TestCase
{
    /**
     * @var Shell&MockObject
     */
    private $shell;
    /**
     * @var SymfonyStyle&MockObject
     */
    private $io;
    /**
     * @var CacheCleaner
     */
    private CacheCleaner $cacheCleaner;

    protected function setUp(): void
    {
        $this->shell = $this->createMock(Shell::class);
        $this->io = $this->createMock(SymfonyStyle::class);
        $this->cacheCleaner = new CacheCleaner($this->shell);
    }

    public function testCleansFrontendCacheTypes(): void
    {
        $this->shell
            ->expects($this->once())
            ->method('execute')
            ->with('bin/magento cache:clean full_page block_html layout translate');

        $this->assertTrue($this->cacheCleaner->clean($this->io, false));
    }

    public function testPrintsProgressInVerboseMode(): void
    {
        $this->io->expects($this->once())->method('text')->with('Cleaning cache...');
        $this->io->expects($this->once())->method('success')->with('Cache cleaned successfully.');

        $this->assertTrue($this->cacheCleaner->clean($this->io, true));
    }

    public function testStaysQuietWhenNotVerbose(): void
    {
        $this->io->expects($this->never())->method('text');
        $this->io->expects($this->never())->method('success');

        $this->assertTrue($this->cacheCleaner->clean($this->io, false));
    }

    public function testReturnsFalseAndPrintsErrorWhenShellFails(): void
    {
        $this->shell->method('execute')->willThrowException(new \RuntimeException('cache backend gone'));
        $this->io->expects($this->once())->method('error')->with('Failed to clean cache: cache backend gone');

        $this->assertFalse($this->cacheCleaner->clean($this->io, true));
    }
}
