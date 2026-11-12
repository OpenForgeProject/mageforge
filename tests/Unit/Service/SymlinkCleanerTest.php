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
    private const SYMLINK_MODE = 0o12_0777;
    private const REGULAR_FILE_MODE = 0o10_0644;

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
            ->method('stat')
            ->willReturnMap([
                ['/theme/web/css/styles.css', ['mode' => self::REGULAR_FILE_MODE]],
                ['/theme/web/css/link.css', ['mode' => self::SYMLINK_MODE]],
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
        $this->fileDriver->method('stat')->willReturn(['mode' => self::REGULAR_FILE_MODE]);
        $io = $this->createMock(SymfonyStyle::class);
        $io->expects($this->never())->method('success');

        $this->assertTrue($this->cleaner->cleanSymlinks('/theme', $io, true));
    }

    public function testTreatsUnstatableItemsAsRegularFiles(): void
    {
        $this->state->method('getMode')->willReturn(State::MODE_DEVELOPER);
        $fileDriver = $this->createMock(File::class);
        $fileDriver->method('isDirectory')->willReturn(true);
        $fileDriver->method('readDirectory')->willReturn(['/theme/web/css/broken.css']);
        $fileDriver->method('stat')->willThrowException(new \RuntimeException('stat failed'));
        $fileDriver->expects($this->never())->method('deleteFile');

        $this->assertTrue($this->createCleaner(fileDriver: $fileDriver)->cleanSymlinks('/theme', $this->io, false));
    }

    public function testReportsRemovedSymlinksInVerboseMode(): void
    {
        $this->state->method('getMode')->willReturn(State::MODE_DEVELOPER);
        $this->fileDriver->method('isDirectory')->willReturn(true);
        $this->fileDriver->method('readDirectory')->willReturn(['/theme/web/css/link.css']);
        $this->fileDriver->method('stat')->willReturn(['mode' => self::SYMLINK_MODE]);
        $io = $this->createMock(SymfonyStyle::class);
        $io->expects($this->once())->method('writeln')->with('  <fg=yellow>⚠</> Removed symlink: link.css');
        $io->expects($this->once())->method('success')->with('Removed 1 symlink(s) from web/css/');

        $this->assertTrue($this->cleaner->cleanSymlinks('/theme', $io, true));
    }

    public function testSucceedsWithWarningWhenDirectoryCannotBeRead(): void
    {
        $this->state->method('getMode')->willReturn(State::MODE_DEVELOPER);
        $this->fileDriver->method('isDirectory')->willReturn(true);
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
        $this->fileDriver->method('stat')->willReturn(['mode' => self::SYMLINK_MODE]);
        $io = $this->createMock(SymfonyStyle::class);
        $io->expects($this->once())->method('writeln')->with('  <fg=yellow>⚠</> Removed symlink: link.css');

        $this->assertTrue($this->cleaner->cleanSymlinks('/theme', $io, true));
    }

    private function createCleaner(?File $fileDriver = null, ?State $state = null): SymlinkCleaner
    {
        return new SymlinkCleaner($fileDriver ?? $this->fileDriver, $state ?? $this->state);
    }
}
