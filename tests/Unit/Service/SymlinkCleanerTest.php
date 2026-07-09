<?php

declare(strict_types=1);

namespace OpenForgeProject\MageForge\Test\Unit\Service;

use Magento\Framework\App\State;
use Magento\Framework\Filesystem\Driver\File;
use OpenForgeProject\MageForge\Service\SymlinkCleaner;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Console\Style\SymfonyStyle;

class SymlinkCleanerTest extends TestCase
{
    private const SYMLINK_MODE = 0o12_0777;
    private const REGULAR_FILE_MODE = 0o10_0644;

    /**
     * @var File&MockObject
     */
    private $fileDriver;
    /**
     * @var State&MockObject
     */
    private $state;
    /**
     * @var SymfonyStyle&MockObject
     */
    private $io;
    /**
     * @var SymlinkCleaner
     */
    private SymlinkCleaner $cleaner;

    protected function setUp(): void
    {
        $this->fileDriver = $this->createMock(File::class);
        $this->state = $this->createMock(State::class);
        $this->io = $this->createMock(SymfonyStyle::class);
        $this->cleaner = new SymlinkCleaner($this->fileDriver, $this->state);
    }

    public function testDoesNothingOutsideDeveloperMode(): void
    {
        $this->state->method('getMode')->willReturn(State::MODE_PRODUCTION);
        $this->fileDriver->expects($this->never())->method('isDirectory');

        $this->assertTrue($this->cleaner->cleanSymlinks('/theme', $this->io, false));
    }

    public function testDoesNothingWhenCssDirectoryIsMissing(): void
    {
        $this->state->method('getMode')->willReturn(State::MODE_DEVELOPER);
        $this->fileDriver->expects($this->once())->method('isDirectory')->with('/theme/web/css')->willReturn(false);
        $this->fileDriver->expects($this->never())->method('readDirectory');

        $this->assertTrue($this->cleaner->cleanSymlinks('/theme/', $this->io, false));
    }

    public function testDeletesOnlySymlinks(): void
    {
        $this->state->method('getMode')->willReturn(State::MODE_DEVELOPER);
        $this->fileDriver->method('isDirectory')->willReturn(true);
        $this->fileDriver
            ->method('readDirectory')
            ->willReturn(['/theme/web/css/styles.css', '/theme/web/css/link.css']);
        $this->fileDriver
            ->method('stat')
            ->willReturnMap([
                ['/theme/web/css/styles.css', ['mode' => self::REGULAR_FILE_MODE]],
                ['/theme/web/css/link.css', ['mode' => self::SYMLINK_MODE]],
            ]);
        $this->fileDriver->expects($this->once())->method('deleteFile')->with('/theme/web/css/link.css');
        $this->io->expects($this->never())->method('writeln');
        $this->io->expects($this->never())->method('success');

        $this->assertTrue($this->cleaner->cleanSymlinks('/theme', $this->io, false));
    }

    public function testVerboseModeWithoutSymlinksPrintsNoSuccess(): void
    {
        $this->state->method('getMode')->willReturn(State::MODE_DEVELOPER);
        $this->fileDriver->method('isDirectory')->willReturn(true);
        $this->fileDriver->method('readDirectory')->willReturn(['/theme/web/css/styles.css']);
        $this->fileDriver->method('stat')->willReturn(['mode' => self::REGULAR_FILE_MODE]);
        $this->io->expects($this->never())->method('success');

        $this->assertTrue($this->cleaner->cleanSymlinks('/theme', $this->io, true));
    }

    public function testTreatsUnstatableItemsAsRegularFiles(): void
    {
        $this->state->method('getMode')->willReturn(State::MODE_DEVELOPER);
        $this->fileDriver->method('isDirectory')->willReturn(true);
        $this->fileDriver->method('readDirectory')->willReturn(['/theme/web/css/broken.css']);
        $this->fileDriver->method('stat')->willThrowException(new \RuntimeException('stat failed'));
        $this->fileDriver->expects($this->never())->method('deleteFile');

        $this->assertTrue($this->cleaner->cleanSymlinks('/theme', $this->io, false));
    }

    public function testReportsRemovedSymlinksInVerboseMode(): void
    {
        $this->state->method('getMode')->willReturn(State::MODE_DEVELOPER);
        $this->fileDriver->method('isDirectory')->willReturn(true);
        $this->fileDriver->method('readDirectory')->willReturn(['/theme/web/css/link.css']);
        $this->fileDriver->method('stat')->willReturn(['mode' => self::SYMLINK_MODE]);
        $this->io->expects($this->once())->method('writeln')->with('  <fg=yellow>⚠</> Removed symlink: link.css');
        $this->io->expects($this->once())->method('success')->with('Removed 1 symlink(s) from web/css/');

        $this->assertTrue($this->cleaner->cleanSymlinks('/theme', $this->io, true));
    }

    public function testSucceedsWithWarningWhenDirectoryCannotBeRead(): void
    {
        $this->state->method('getMode')->willReturn(State::MODE_DEVELOPER);
        $this->fileDriver->method('isDirectory')->willReturn(true);
        $this->fileDriver->method('readDirectory')->willThrowException(new \RuntimeException('permission denied'));
        $this->io->expects($this->once())->method('warning')->with('Could not clean symlinks: permission denied');

        $this->assertTrue($this->cleaner->cleanSymlinks('/theme', $this->io, true));
    }

    public function testReportsBasenameForItemWithoutDirectorySeparator(): void
    {
        $this->state->method('getMode')->willReturn(State::MODE_DEVELOPER);
        $this->fileDriver->method('isDirectory')->willReturn(true);
        $this->fileDriver->method('readDirectory')->willReturn(['link.css']);
        $this->fileDriver->method('stat')->willReturn(['mode' => self::SYMLINK_MODE]);
        $this->io->expects($this->once())->method('writeln')->with('  <fg=yellow>⚠</> Removed symlink: link.css');

        $this->assertTrue($this->cleaner->cleanSymlinks('/theme', $this->io, true));
    }
}
