<?php

declare(strict_types=1);

namespace OpenForgeProject\MageForge\Test\Unit\Service;

use Magento\Framework\App\Filesystem\DirectoryList;
use Magento\Framework\Filesystem;
use Magento\Framework\Filesystem\Directory\ReadInterface;
use Magento\Framework\Filesystem\Directory\WriteInterface;
use OpenForgeProject\MageForge\Service\ThemeCleaner;
use PHPUnit\Framework\MockObject\Stub;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Console\Style\SymfonyStyle;

class ThemeCleanerTest extends TestCase
{
    /**
     * @var WriteInterface&Stub
     */
    private $writeDirectory;
    /**
     * @var SymfonyStyle&Stub
     */
    private $io;
    /**
     * @var ThemeCleaner
     */
    private ThemeCleaner $cleaner;

    protected function setUp(): void
    {
        $this->writeDirectory = $this->createStub(WriteInterface::class);
        $this->io = $this->createStub(SymfonyStyle::class);
        $this->cleaner = $this->createCleaner();
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
        $io = $this->createMock(SymfonyStyle::class);
        $io->expects($this->never())->method('writeln');

        $this->assertSame(2, $this->cleaner->cleanViewPreprocessed('Vendor/theme', $io));
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
        $writeDirectory = $this->createMock(WriteInterface::class);
        $writeDirectory->method('isDirectory')->willReturn(true);
        $writeDirectory->expects($this->never())->method('delete');
        $cleaner = $this->createCleaner($writeDirectory);

        $this->assertSame(0, $cleaner->cleanViewPreprocessed('invalid-theme-code', $this->io));
        $this->assertSame(0, $cleaner->cleanViewPreprocessed('too/many/parts', $this->io));
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

    public function testRecordsDeleteFailuresEvenWithoutVerboseOutput(): void
    {
        $this->writeDirectory->method('isDirectory')->willReturn(true);
        $this->writeDirectory->method('delete')->willThrowException(new \RuntimeException('locked'));
        $io = $this->createMock(SymfonyStyle::class);
        $io->expects($this->never())->method('writeln');

        $this->cleaner->cleanPubStatic('Vendor/theme', $io);
        $this->cleaner->cleanPageCache($io);

        $this->assertSame(
            ['pub/static/frontend/Vendor/theme - locked', 'var/page_cache - locked'],
            $this->cleaner->getFailures(),
        );
    }

    public function testResetFailuresForgetsRecordedFailures(): void
    {
        $this->writeDirectory->method('isDirectory')->willReturn(true);
        $this->writeDirectory->method('delete')->willThrowException(new \RuntimeException('locked'));

        $this->cleaner->cleanVarTmp($this->io);
        $this->assertCount(1, $this->cleaner->getFailures());

        $this->cleaner->resetFailures();

        $this->assertSame([], $this->cleaner->getFailures());
    }

    public function testDryRunAndSuccessfulDeletionsRecordNoFailures(): void
    {
        $this->writeDirectory->method('isDirectory')->willReturn(true);

        $this->cleaner->cleanPageCache($this->io, true);
        $this->cleaner->cleanVarTmp($this->io);

        $this->assertSame([], $this->cleaner->getFailures());
    }

    public function testCleanViewPreprocessedReturnsZeroWhenBaseDirectoryMissing(): void
    {
        $writeDirectory = $this->createMock(WriteInterface::class);
        $writeDirectory->method('isDirectory')->willReturn(false);
        $writeDirectory->expects($this->never())->method('delete');

        $this->assertSame(0, $this->createCleaner($writeDirectory)->cleanViewPreprocessed('Vendor/theme', $this->io));
    }

    public function testDryRunCountsWithoutDeleting(): void
    {
        $writeDirectory = $this->createMock(WriteInterface::class);
        $writeDirectory->method('isDirectory')->willReturn(true);
        $writeDirectory->expects($this->never())->method('delete');

        $cleaner = $this->createCleaner($writeDirectory);

        $this->assertSame(2, $cleaner->cleanViewPreprocessed('Vendor/theme', $this->io, dryRun: true));
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
        $writeDirectory = $this->createMock(WriteInterface::class);
        $writeDirectory->method('isDirectory')->willReturn(true);
        $writeDirectory
            ->expects($this->once())
            ->method('delete')
            ->with('frontend/Vendor/theme')
            ->willReturn(true);
        $io = $this->createMock(SymfonyStyle::class);
        $io->expects($this->never())->method('writeln');

        $this->assertSame(1, $this->createCleaner($writeDirectory)->cleanPubStatic('Vendor/theme', $io));
    }

    public function testCleanPubStaticReturnsZeroWhenThemeDirectoryMissing(): void
    {
        $writeDirectory = $this->createMock(WriteInterface::class);
        $writeDirectory
            ->method('isDirectory')
            ->willReturnMap([
                ['frontend', true],
                ['frontend/Vendor/theme', false],
            ]);
        $writeDirectory->expects($this->never())->method('delete');

        $this->assertSame(0, $this->createCleaner($writeDirectory)->cleanPubStatic('Vendor/theme', $this->io));
    }

    public function testCleanPubStaticReportsVerboseOutput(): void
    {
        $this->writeDirectory->method('isDirectory')->willReturn(true);
        $this->writeDirectory->method('delete')->willReturn(true);
        $io = $this->createMock(SymfonyStyle::class);
        $io
            ->expects($this->once())
            ->method('writeln')
            ->with('  <fg=green>✓</> Cleaned: pub/static/frontend/Vendor/theme');

        $this->assertSame(1, $this->cleaner->cleanPubStatic('Vendor/theme', $io, false, true));
    }

    // -------------------------------------------------------------------------
    // cleanPageCache / cleanVarTmp
    // -------------------------------------------------------------------------

    public function testCleansPageCacheWhenPresent(): void
    {
        $writeDirectory = $this->createMock(WriteInterface::class);
        $writeDirectory->expects($this->once())->method('isDirectory')->with('page_cache')->willReturn(true);
        $writeDirectory->expects($this->once())->method('delete')->with('page_cache')->willReturn(true);
        $io = $this->createMock(SymfonyStyle::class);
        $io->expects($this->never())->method('writeln');

        $this->assertSame(1, $this->createCleaner($writeDirectory)->cleanPageCache($io));
    }

    public function testCleanPageCacheReturnsZeroWhenMissing(): void
    {
        $this->writeDirectory->method('isDirectory')->willReturn(false);

        $this->assertSame(0, $this->cleaner->cleanPageCache($this->io));
    }

    public function testCleansVarTmpWhenPresent(): void
    {
        $writeDirectory = $this->createMock(WriteInterface::class);
        $writeDirectory->expects($this->once())->method('isDirectory')->with('tmp')->willReturn(true);
        $writeDirectory->expects($this->once())->method('delete')->with('tmp')->willReturn(true);
        $io = $this->createMock(SymfonyStyle::class);
        $io->expects($this->never())->method('writeln');

        $this->assertSame(1, $this->createCleaner($writeDirectory)->cleanVarTmp($io));
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

        $io = $this->createMock(SymfonyStyle::class);
        $io->expects($this->never())->method('writeln');

        $this->assertSame(1, $this->cleaner->cleanGenerated($io));
        $this->assertSame(['code', 'metadata'], $deleted);
    }

    public function testCleanGeneratedReturnsZeroWhenNothingExists(): void
    {
        $writeDirectory = $this->createMock(WriteInterface::class);
        $writeDirectory->method('isDirectory')->willReturn(false);
        $writeDirectory->expects($this->never())->method('delete');

        $this->assertSame(0, $this->createCleaner($writeDirectory)->cleanGenerated($this->io));
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
        $readDirectory->expects($this->once())->method('isDirectory')->with('frontend/Vendor/theme')->willReturn(true);
        $filesystem = $this->createMock(Filesystem::class);
        $filesystem
            ->expects($this->once())
            ->method('getDirectoryRead')
            ->with(DirectoryList::STATIC_VIEW)
            ->willReturn($readDirectory);

        $this->assertTrue((new ThemeCleaner($filesystem))->hasStaticFiles('Vendor/theme'));
    }

    public function testHasStaticFilesIsFalseForInvalidThemeCode(): void
    {
        $filesystem = $this->createMock(Filesystem::class);
        $filesystem->expects($this->never())->method('getDirectoryRead');

        $this->assertFalse((new ThemeCleaner($filesystem))->hasStaticFiles('invalid'));
    }

    private function createCleaner(?WriteInterface $writeDirectory = null): ThemeCleaner
    {
        $filesystem = $this->createStub(Filesystem::class);
        $filesystem->method('getDirectoryWrite')->willReturn($writeDirectory ?? $this->writeDirectory);

        return new ThemeCleaner($filesystem);
    }
}
