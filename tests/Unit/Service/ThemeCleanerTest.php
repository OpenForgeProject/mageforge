<?php

declare(strict_types=1);

namespace OpenForgeProject\MageForge\Test\Unit\Service;

use Magento\Framework\App\Filesystem\DirectoryList;
use Magento\Framework\Filesystem;
use Magento\Framework\Filesystem\Directory\ReadInterface;
use Magento\Framework\Filesystem\Directory\WriteInterface;
use OpenForgeProject\MageForge\Service\ThemeCleaner;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Console\Style\SymfonyStyle;

class ThemeCleanerTest extends TestCase
{
    /**
     * @var Filesystem&MockObject
     */
    private $filesystem;
    /**
     * @var WriteInterface&MockObject
     */
    private $writeDirectory;
    /**
     * @var SymfonyStyle&MockObject
     */
    private $io;
    /**
     * @var ThemeCleaner
     */
    private ThemeCleaner $cleaner;

    protected function setUp(): void
    {
        $this->filesystem = $this->createMock(Filesystem::class);
        $this->writeDirectory = $this->createMock(WriteInterface::class);
        $this->filesystem->method('getDirectoryWrite')->willReturn($this->writeDirectory);
        $this->io = $this->createMock(SymfonyStyle::class);
        $this->cleaner = new ThemeCleaner($this->filesystem);
    }

    // -------------------------------------------------------------------------
    // cleanViewPreprocessed
    // -------------------------------------------------------------------------

    public function testCleansPreprocessedCssAndSourceDirectories(): void
    {
        $this->writeDirectory->method('isDirectory')->willReturn(true);
        $deleted = [];
        $this->writeDirectory
            ->method('delete')
            ->willReturnCallback(function (string $path) use (&$deleted): bool {
                $deleted[] = $path;
                return true;
            });
        $this->io->expects($this->never())->method('writeln');

        $this->assertSame(2, $this->cleaner->cleanViewPreprocessed('Vendor/theme', $this->io));
        $this->assertSame(
            [
                'view_preprocessed/css/frontend/Vendor/theme',
                'view_preprocessed/source/frontend/Vendor/theme',
            ],
            $deleted,
        );
    }

    public function testCleanViewPreprocessedReturnsZeroForInvalidThemeCode(): void
    {
        // Directories nominally exist so only the theme-code guard prevents deletion.
        $this->writeDirectory->method('isDirectory')->willReturn(true);
        $this->writeDirectory->expects($this->never())->method('delete');

        $this->assertSame(0, $this->cleaner->cleanViewPreprocessed('invalid-theme-code', $this->io));
        $this->assertSame(0, $this->cleaner->cleanViewPreprocessed('too/many/parts', $this->io));
    }

    public function testVerboseDryRunAnnouncesWhatWouldBeCleaned(): void
    {
        $this->writeDirectory->method('isDirectory')->willReturn(true);

        $lines = [];
        $this->io
            ->method('writeln')
            ->willReturnCallback(function (string $line) use (&$lines): void {
                $lines[] = $line;
            });

        $this->assertSame(2, $this->cleaner->cleanViewPreprocessed('Vendor/theme', $this->io, true, true));
        $this->assertSame(
            [
                '  <fg=green>✓</> Would clean: var/view_preprocessed/css/frontend/Vendor/theme',
                '  <fg=green>✓</> Would clean: var/view_preprocessed/source/frontend/Vendor/theme',
            ],
            $lines,
        );
    }

    public function testVerboseModeReportsDeleteFailures(): void
    {
        $this->writeDirectory->method('isDirectory')->willReturn(true);
        $this->writeDirectory->method('delete')->willThrowException(new \RuntimeException('locked'));

        $lines = [];
        $this->io
            ->method('writeln')
            ->willReturnCallback(function (string $line) use (&$lines): void {
                $lines[] = $line;
            });

        $this->assertSame(0, $this->cleaner->cleanViewPreprocessed('Vendor/theme', $this->io, false, true));
        $this->assertSame(
            [
                '  <fg=red>✗</> Failed to clean: var/view_preprocessed/css/frontend/Vendor/theme - locked',
                '  <fg=red>✗</> Failed to clean: var/view_preprocessed/source/frontend/Vendor/theme - locked',
            ],
            $lines,
        );
    }

    public function testCleanViewPreprocessedReturnsZeroWhenBaseDirectoryMissing(): void
    {
        $this->writeDirectory->method('isDirectory')->willReturn(false);
        $this->writeDirectory->expects($this->never())->method('delete');

        $this->assertSame(0, $this->cleaner->cleanViewPreprocessed('Vendor/theme', $this->io));
    }

    public function testDryRunCountsWithoutDeleting(): void
    {
        $this->writeDirectory->method('isDirectory')->willReturn(true);
        $this->writeDirectory->expects($this->never())->method('delete');

        $this->assertSame(2, $this->cleaner->cleanViewPreprocessed('Vendor/theme', $this->io, dryRun: true));
    }

    public function testCleanViewPreprocessedSurvivesDeleteFailure(): void
    {
        $this->writeDirectory->method('isDirectory')->willReturn(true);
        $this->writeDirectory->method('delete')->willThrowException(new \RuntimeException('locked'));

        $this->assertSame(0, $this->cleaner->cleanViewPreprocessed('Vendor/theme', $this->io));
    }

    // -------------------------------------------------------------------------
    // cleanPubStatic
    // -------------------------------------------------------------------------

    public function testCleansPubStaticThemeDirectory(): void
    {
        $this->writeDirectory->method('isDirectory')->willReturn(true);
        $this->writeDirectory
            ->expects($this->once())
            ->method('delete')
            ->with('frontend/Vendor/theme')
            ->willReturn(true);
        $this->io->expects($this->never())->method('writeln');

        $this->assertSame(1, $this->cleaner->cleanPubStatic('Vendor/theme', $this->io));
    }

    public function testCleanPubStaticReturnsZeroWhenThemeDirectoryMissing(): void
    {
        $this->writeDirectory
            ->method('isDirectory')
            ->willReturnMap([
                ['frontend', true],
                ['frontend/Vendor/theme', false],
            ]);
        $this->writeDirectory->expects($this->never())->method('delete');

        $this->assertSame(0, $this->cleaner->cleanPubStatic('Vendor/theme', $this->io));
    }

    public function testCleanPubStaticReportsVerboseOutput(): void
    {
        $this->writeDirectory->method('isDirectory')->willReturn(true);
        $this->writeDirectory->method('delete')->willReturn(true);
        $this->io
            ->expects($this->once())
            ->method('writeln')
            ->with('  <fg=green>✓</> Cleaned: pub/static/frontend/Vendor/theme');

        $this->assertSame(1, $this->cleaner->cleanPubStatic('Vendor/theme', $this->io, false, true));
    }

    // -------------------------------------------------------------------------
    // cleanPageCache / cleanVarTmp
    // -------------------------------------------------------------------------

    public function testCleansPageCacheWhenPresent(): void
    {
        $this->writeDirectory->method('isDirectory')->with('page_cache')->willReturn(true);
        $this->writeDirectory->expects($this->once())->method('delete')->with('page_cache')->willReturn(true);
        $this->io->expects($this->never())->method('writeln');

        $this->assertSame(1, $this->cleaner->cleanPageCache($this->io));
    }

    public function testCleanPageCacheReturnsZeroWhenMissing(): void
    {
        $this->writeDirectory->method('isDirectory')->willReturn(false);

        $this->assertSame(0, $this->cleaner->cleanPageCache($this->io));
    }

    public function testCleansVarTmpWhenPresent(): void
    {
        $this->writeDirectory->method('isDirectory')->with('tmp')->willReturn(true);
        $this->writeDirectory->expects($this->once())->method('delete')->with('tmp')->willReturn(true);
        $this->io->expects($this->never())->method('writeln');

        $this->assertSame(1, $this->cleaner->cleanVarTmp($this->io));
    }

    // -------------------------------------------------------------------------
    // cleanGenerated
    // -------------------------------------------------------------------------

    public function testCleansGeneratedCodeAndMetadataAsOneUnit(): void
    {
        $this->writeDirectory->method('isDirectory')->willReturn(true);
        $deleted = [];
        $this->writeDirectory
            ->method('delete')
            ->willReturnCallback(function (string $path) use (&$deleted): bool {
                $deleted[] = $path;
                return true;
            });

        $this->io->expects($this->never())->method('writeln');

        $this->assertSame(1, $this->cleaner->cleanGenerated($this->io));
        $this->assertSame(['code', 'metadata'], $deleted);
    }

    public function testCleanGeneratedReturnsZeroWhenNothingExists(): void
    {
        $this->writeDirectory->method('isDirectory')->willReturn(false);
        $this->writeDirectory->expects($this->never())->method('delete');

        $this->assertSame(0, $this->cleaner->cleanGenerated($this->io));
    }

    public function testCleanGeneratedCountsPartialSuccess(): void
    {
        $this->writeDirectory->method('isDirectory')->willReturn(true);
        $this->writeDirectory
            ->method('delete')
            ->willReturnCallback(static function (string $path): bool {
                if ($path === 'code') {
                    throw new \RuntimeException('permission denied');
                }
                return true;
            });

        $this->assertSame(1, $this->cleaner->cleanGenerated($this->io));
    }

    // -------------------------------------------------------------------------
    // hasStaticFiles
    // -------------------------------------------------------------------------

    public function testHasStaticFilesChecksThemeDirectory(): void
    {
        $readDirectory = $this->createMock(ReadInterface::class);
        $this->filesystem
            ->method('getDirectoryRead')
            ->with(DirectoryList::STATIC_VIEW)
            ->willReturn($readDirectory);
        $readDirectory->method('isDirectory')->with('frontend/Vendor/theme')->willReturn(true);

        $this->assertTrue($this->cleaner->hasStaticFiles('Vendor/theme'));
    }

    public function testHasStaticFilesIsFalseForInvalidThemeCode(): void
    {
        $this->filesystem->expects($this->never())->method('getDirectoryRead');

        $this->assertFalse($this->cleaner->hasStaticFiles('invalid'));
    }
}
