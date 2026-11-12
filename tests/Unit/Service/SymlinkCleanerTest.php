<?php

declare(strict_types=1);

namespace OpenForgeProject\MageForge\Test\Unit\Service;

use Magento\Framework\App\State;
use Magento\Framework\Filesystem\Driver\File;
use OpenForgeProject\MageForge\Service\SymlinkCleaner;
use PHPUnit\Framework\MockObject\Stub;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Console\Style\SymfonyStyle;

class SymlinkCleanerTest extends TestCase
{
    /**
     * @var File&Stub
     */
    private $fileDriver;
    /**
     * @var State&Stub
     */
    private $state;
    /**
     * @var SymfonyStyle&Stub
     */
    private $io;
    /**
     * @var SymlinkCleaner
     */
    private SymlinkCleaner $cleaner;

    protected function setUp(): void
    {
        $this->fileDriver = $this->createStub(File::class);
        $this->state = $this->createStub(State::class);
        $this->io = $this->createStub(SymfonyStyle::class);
        $this->cleaner = $this->createCleaner();
    }

    public function testDoesNothingOutsideDeveloperMode(): void
    {
        $this->state->method('getMode')->willReturn(State::MODE_PRODUCTION);
        $fileDriver = $this->createMock(File::class);
        $fileDriver->expects($this->never())->method('isDirectory');

        $this->assertTrue($this->createCleaner(fileDriver: $fileDriver)->cleanSymlinks('/theme', $this->io, false));
    }

    public function testDoesNothingWhenCssDirectoryIsMissing(): void
    {
        $this->state->method('getMode')->willReturn(State::MODE_DEVELOPER);
        $fileDriver = $this->createMock(File::class);
        $fileDriver->expects($this->once())->method('isDirectory')->with('/theme/web/css')->willReturn(false);
        $fileDriver->expects($this->never())->method('readDirectory');

        $this->assertTrue($this->createCleaner(fileDriver: $fileDriver)->cleanSymlinks('/theme/', $this->io, false));
    }

    public function testDeletesOnlySymlinks(): void
    {
        $this->state->method('getMode')->willReturn(State::MODE_DEVELOPER);
        $fileDriver = $this->createMock(File::class);
        $fileDriver->method('isDirectory')->willReturn(true);
        $fileDriver
            ->method('readDirectory')
            ->willReturn(['/theme/web/css/styles.css', '/theme/web/css/link.css']);
        $fileDriver
            ->method('getRealPath')
            ->willReturnMap([
                ['/theme/web/css', '/theme/web/css'],
                ['/theme/web/css/styles.css', '/theme/web/css/styles.css'],
                ['/theme/web/css/link.css', '/shared/link.css'],
            ]);
        $fileDriver->expects($this->once())->method('deleteFile')->with('/theme/web/css/link.css');
        $io = $this->createMock(SymfonyStyle::class);
        $io->expects($this->never())->method('writeln');
        $io->expects($this->never())->method('success');

        $this->assertTrue($this->createCleaner(fileDriver: $fileDriver)->cleanSymlinks('/theme', $io, false));
    }

    public function testVerboseModeWithoutSymlinksPrintsNoSuccess(): void
    {
        $this->state->method('getMode')->willReturn(State::MODE_DEVELOPER);
        $this->fileDriver->method('isDirectory')->willReturn(true);
        $this->fileDriver->method('readDirectory')->willReturn(['/theme/web/css/styles.css']);
        $this->fileDriver->method('getRealPath')->willReturnArgument(0);
        $io = $this->createMock(SymfonyStyle::class);
        $io->expects($this->never())->method('success');

        $this->assertTrue($this->cleaner->cleanSymlinks('/theme', $io, true));
    }

    public function testDeletesBrokenSymlinks(): void
    {
        $this->state->method('getMode')->willReturn(State::MODE_DEVELOPER);
        $fileDriver = $this->createMock(File::class);
        $fileDriver->method('isDirectory')->willReturn(true);
        $fileDriver->method('readDirectory')->willReturn(['/theme/web/css/broken.css']);
        $fileDriver
            ->method('getRealPath')
            ->willReturnMap([
                ['/theme/web/css', '/theme/web/css'],
                ['/theme/web/css/broken.css', false],
            ]);
        $fileDriver->expects($this->once())->method('deleteFile')->with('/theme/web/css/broken.css');

        $this->assertTrue($this->createCleaner(fileDriver: $fileDriver)->cleanSymlinks('/theme', $this->io, false));
    }

    public function testKeepsRegularFilesInsideSymlinkedThemeDirectory(): void
    {
        $this->state->method('getMode')->willReturn(State::MODE_DEVELOPER);
        $fileDriver = $this->createMock(File::class);
        $fileDriver->method('isDirectory')->willReturn(true);
        $fileDriver->method('readDirectory')->willReturn(['/theme/web/css/styles.css']);
        $fileDriver
            ->method('getRealPath')
            ->willReturnMap([
                ['/theme/web/css', '/real/theme/web/css'],
                ['/theme/web/css/styles.css', '/real/theme/web/css/styles.css'],
            ]);
        $fileDriver->expects($this->never())->method('deleteFile');

        $this->assertTrue($this->createCleaner(fileDriver: $fileDriver)->cleanSymlinks('/theme', $this->io, false));
    }

    public function testRemovesRealSymlinksFromFilesystem(): void
    {
        $state = $this->createStub(State::class);
        $state->method('getMode')->willReturn(State::MODE_DEVELOPER);
        $themePath = sys_get_temp_dir() . '/mageforge-symlink-' . uniqid();
        $cssPath = $themePath . '/web/css';
        mkdir($cssPath, 0o777, true);
        file_put_contents($cssPath . '/styles.css', 'a{}');
        file_put_contents($themePath . '/target.css', 'b{}');
        symlink($themePath . '/target.css', $cssPath . '/link.css');
        symlink($themePath . '/missing.css', $cssPath . '/broken.css');

        try {
            $cleaner = $this->createCleaner(fileDriver: new File(), state: $state);
            $this->assertTrue($cleaner->cleanSymlinks($themePath, $this->io, false));

            $this->assertFileExists($cssPath . '/styles.css');
            $this->assertFileExists($themePath . '/target.css');
            $this->assertFalse(is_link($cssPath . '/link.css'));
            $this->assertFalse(is_link($cssPath . '/broken.css'));
        } finally {
            array_map('unlink', glob($cssPath . '/*') ?: []);
            unlink($themePath . '/target.css');
            rmdir($cssPath);
            rmdir($themePath . '/web');
            rmdir($themePath);
        }
    }

    public function testReportsRemovedSymlinksInVerboseMode(): void
    {
        $this->state->method('getMode')->willReturn(State::MODE_DEVELOPER);
        $this->fileDriver->method('isDirectory')->willReturn(true);
        $this->fileDriver->method('readDirectory')->willReturn(['/theme/web/css/link.css']);
        $this->fileDriver->method('getRealPath')->willReturnMap([
            ['/theme/web/css', '/theme/web/css'],
            ['/theme/web/css/link.css', '/shared/link.css'],
        ]);
        $io = $this->createMock(SymfonyStyle::class);
        $io->expects($this->once())->method('writeln')->with('  <fg=yellow>⚠</> Removed symlink: link.css');
        $io->expects($this->once())->method('success')->with('Removed 1 symlink(s) from web/css/');

        $this->assertTrue($this->cleaner->cleanSymlinks('/theme', $io, true));
    }

    public function testSucceedsWithWarningWhenDirectoryCannotBeRead(): void
    {
        $this->state->method('getMode')->willReturn(State::MODE_DEVELOPER);
        $this->fileDriver->method('isDirectory')->willReturn(true);
        $this->fileDriver->method('getRealPath')->willReturnArgument(0);
        $this->fileDriver->method('readDirectory')->willThrowException(new \RuntimeException('permission denied'));
        $io = $this->createMock(SymfonyStyle::class);
        $io->expects($this->once())->method('warning')->with('Could not clean symlinks: permission denied');

        $this->assertTrue($this->cleaner->cleanSymlinks('/theme', $io, true));
    }

    public function testReportsBasenameForItemWithoutDirectorySeparator(): void
    {
        $this->state->method('getMode')->willReturn(State::MODE_DEVELOPER);
        $this->fileDriver->method('isDirectory')->willReturn(true);
        $this->fileDriver->method('readDirectory')->willReturn(['link.css']);
        $this->fileDriver->method('getRealPath')->willReturnMap([
            ['/theme/web/css', '/theme/web/css'],
            ['link.css', '/shared/link.css'],
        ]);
        $io = $this->createMock(SymfonyStyle::class);
        $io->expects($this->once())->method('writeln')->with('  <fg=yellow>⚠</> Removed symlink: link.css');

        $this->assertTrue($this->cleaner->cleanSymlinks('/theme', $io, true));
    }

    private function createCleaner(?File $fileDriver = null, ?State $state = null): SymlinkCleaner
    {
        return new SymlinkCleaner($fileDriver ?? $this->fileDriver, $state ?? $this->state);
    }
}
