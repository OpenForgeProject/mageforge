<?php

declare(strict_types=1);

namespace OpenForgeProject\MageForge\Test\Unit\Service;

use Magento\Framework\Filesystem\Driver\File;
use Magento\Framework\Shell;
use OpenForgeProject\MageForge\Service\DependencyChecker;
use PHPUnit\Framework\MockObject\Stub;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Console\Style\SymfonyStyle;

class DependencyCheckerTest extends TestCase
{
    /**
     * @var File&Stub
     */
    private $fileDriver;
    /**
     * @var Shell&Stub
     */
    private $shell;
    /**
     * @var SymfonyStyle&Stub
     */
    private $io;
    /**
     * @var DependencyChecker
     */
    private DependencyChecker $checker;

    protected function setUp(): void
    {
        $this->fileDriver = $this->createStub(File::class);
        $this->shell = $this->createStub(Shell::class);
        $this->io = $this->createStub(SymfonyStyle::class);
        $this->checker = $this->createChecker();
    }

    private function createChecker(?File $fileDriver = null, ?Shell $shell = null): DependencyChecker
    {
        return new DependencyChecker($fileDriver ?? $this->fileDriver, $shell ?? $this->shell);
    }

    public function testSucceedsWhenAllDependenciesArePresent(): void
    {
        $fileDriver = $this->createMock(File::class);
        $this->givenFiles($fileDriver, ['package.json' => true, 'Gruntfile.js' => true]);
        $fileDriver->expects($this->once())->method('isDirectory')->with('node_modules')->willReturn(true);
        $io = $this->createMock(SymfonyStyle::class);
        $io->expects($this->never())->method('error');
        $io->expects($this->never())->method('success');
        $io->expects($this->never())->method('warning');

        $checker = $this->createChecker(fileDriver: $fileDriver);

        $this->assertTrue($checker->checkDependencies($io, false));
    }

    public function testReportsEveryFoundDependencyInVerboseMode(): void
    {
        $fileDriver = $this->createMock(File::class);
        $this->givenFiles($fileDriver, ['package.json' => true, 'Gruntfile.js' => true]);
        $fileDriver->expects($this->once())->method('isDirectory')->with('node_modules')->willReturn(true);

        $successMessages = [];
        $this->io
            ->method('success')
            ->willReturnCallback(function (string $message) use (&$successMessages): void {
                $successMessages[] = $message;
            });

        $checker = $this->createChecker(fileDriver: $fileDriver);

        $this->assertTrue($checker->checkDependencies($this->io, true));
        $this->assertSame(
            [
                "The 'package.json' file found.",
                "The 'node_modules' folder found.",
                "The 'Gruntfile.js' file found.",
            ],
            $successMessages,
        );
    }

    public function testWarnsAboutMissingFilesInVerboseMode(): void
    {
        $this->givenFiles($this->fileDriver, ['package.json' => false, 'package.json.sample' => false]);

        $warnings = [];
        $this->io
            ->method('warning')
            ->willReturnCallback(function (string $message) use (&$warnings): void {
                $warnings[] = $message;
            });

        $this->assertFalse($this->checker->checkDependencies($this->io, true));
        $this->assertSame(
            [
                "The 'package.json' file does not exist in the Magento root path.",
                "The 'package.json.sample' file does not exist in the Magento root path.",
            ],
            $warnings,
        );
    }

    public function testFailsWhenPackageJsonAndSampleAreMissing(): void
    {
        $this->givenFiles($this->fileDriver, ['package.json' => false, 'package.json.sample' => false]);
        $io = $this->createMock(SymfonyStyle::class);
        $io->expects($this->once())->method('error')->with('Skipping this theme build.');

        $this->assertFalse($this->checker->checkDependencies($io, false));
    }

    public function testCopiesPackageJsonFromSampleWhenConfirmed(): void
    {
        $fileDriver = $this->createMock(File::class);
        $this->givenFiles($fileDriver, [
            'package.json' => false,
            'package.json.sample' => true,
            'Gruntfile.js' => true,
        ]);
        $fileDriver->method('isDirectory')->willReturn(true);
        $this->io->method('confirm')->willReturn(true);
        $fileDriver
            ->expects($this->once())
            ->method('copy')
            ->with('package.json.sample', 'package.json');

        $checker = $this->createChecker(fileDriver: $fileDriver);

        $this->assertTrue($checker->checkDependencies($this->io, false));
    }

    public function testRunsNpmInstallWhenNodeModulesMissingAndConfirmed(): void
    {
        $this->givenFiles($this->fileDriver, ['package.json' => true, 'Gruntfile.js' => true]);
        $this->fileDriver->method('isDirectory')->willReturn(false);
        $this->io->method('confirm')->willReturn(true);
        $shell = $this->createMock(Shell::class);
        $shell->expects($this->once())->method('execute')->with('npm install --quiet');

        $checker = $this->createChecker(shell: $shell);

        $this->assertTrue($checker->checkDependencies($this->io, false));
    }

    public function testFailsWhenNpmInstallFails(): void
    {
        $this->givenFiles($this->fileDriver, ['package.json' => true]);
        $this->fileDriver->method('isDirectory')->willReturn(false);
        $io = $this->createMock(SymfonyStyle::class);
        $io->method('confirm')->willReturn(true);
        $this->shell->method('execute')->willThrowException(new \RuntimeException('npm error'));
        $io->expects($this->once())->method('error')->with('npm error');

        $this->assertFalse($this->checker->checkDependencies($io, false));
    }

    public function testFailsWhenNpmInstallIsDeclined(): void
    {
        $this->givenFiles($this->fileDriver, ['package.json' => true]);
        $this->fileDriver->method('isDirectory')->willReturn(false);
        $io = $this->createMock(SymfonyStyle::class);
        $io->method('confirm')->willReturn(false);
        $shell = $this->createMock(Shell::class);
        $shell->expects($this->never())->method('execute');
        $io->expects($this->once())->method('error')->with('Skipping this theme build.');

        $checker = $this->createChecker(shell: $shell);

        $this->assertFalse($checker->checkDependencies($io, false));
    }

    public function testFailsWhenGruntfileAndSampleAreMissing(): void
    {
        $this->givenFiles($this->fileDriver, [
            'package.json' => true,
            'Gruntfile.js' => false,
            'Gruntfile.js.sample' => false,
        ]);
        $this->fileDriver->method('isDirectory')->willReturn(true);
        $io = $this->createMock(SymfonyStyle::class);
        $io->expects($this->once())->method('error')->with('Skipping this theme build.');

        $this->assertFalse($this->checker->checkDependencies($io, false));
    }

    public function testCopiesGruntfileFromSampleWhenConfirmed(): void
    {
        $fileDriver = $this->createMock(File::class);
        $this->givenFiles($fileDriver, [
            'package.json' => true,
            'Gruntfile.js' => false,
            'Gruntfile.js.sample' => true,
        ]);
        $fileDriver->method('isDirectory')->willReturn(true);
        $this->io->method('confirm')->willReturn(true);
        $fileDriver
            ->expects($this->once())
            ->method('copy')
            ->with('Gruntfile.js.sample', 'Gruntfile.js');

        $checker = $this->createChecker(fileDriver: $fileDriver);

        $this->assertTrue($checker->checkDependencies($this->io, false));
    }

    public function testSkippingSampleCopyStillSucceeds(): void
    {
        $fileDriver = $this->createMock(File::class);
        $this->givenFiles($fileDriver, [
            'package.json' => false,
            'package.json.sample' => true,
            'Gruntfile.js' => true,
        ]);
        $fileDriver->method('isDirectory')->willReturn(true);
        $this->io->method('confirm')->willReturn(false);
        $fileDriver->expects($this->never())->method('copy');

        $checker = $this->createChecker(fileDriver: $fileDriver);

        $this->assertTrue($checker->checkDependencies($this->io, false));
    }

    /**
     * Configure the file driver; unlisted files do not exist.
     *
     * @param array<string, bool> $files
     */
    private function givenFiles(File&Stub $fileDriver, array $files): void
    {
        $fileDriver
            ->method('isFile')
            ->willReturnCallback(static fn(string $path): bool => $files[$path] ?? false);
    }

    public function testVerboseSampleCopyFlowReportsEachStep(): void
    {
        $this->givenFiles($this->fileDriver, [
            'package.json' => false,
            'package.json.sample' => true,
            'Gruntfile.js' => true,
        ]);
        $this->fileDriver->method('isDirectory')->willReturn(true);
        $this->io->method('confirm')->willReturn(true);

        $successMessages = [];
        $this->io
            ->method('success')
            ->willReturnCallback(function (string $message) use (&$successMessages): void {
                $successMessages[] = $message;
            });

        $this->assertTrue($this->checker->checkDependencies($this->io, true));
        $this->assertSame(
            [
                "The 'package.json.sample' file found.",
                "'package.json.sample' has been copied to 'package.json'.",
                "The 'node_modules' folder found.",
                "The 'Gruntfile.js' file found.",
            ],
            $successMessages,
        );
    }

    public function testVerboseNpmInstallFlowReportsEachStep(): void
    {
        $this->givenFiles($this->fileDriver, ['package.json' => true, 'Gruntfile.js' => true]);
        $this->fileDriver->method('isDirectory')->willReturn(false);
        $shell = $this->createMock(Shell::class);
        $shell
            ->expects($this->once())
            ->method('execute')
            ->with('npm install --quiet')
            ->willReturn('added 120 packages');
        $io = $this->createMock(SymfonyStyle::class);
        $io->method('confirm')->willReturn(true);
        $io->expects($this->once())->method('section')->with("Running 'npm install'... Please wait.");
        $io->expects($this->once())->method('writeln')->with('added 120 packages');

        $warnings = [];
        $io
            ->method('warning')
            ->willReturnCallback(function (string $message) use (&$warnings): void {
                $warnings[] = $message;
            });

        $checker = $this->createChecker(shell: $shell);

        $this->assertTrue($checker->checkDependencies($io, true));
        $this->assertSame(["The 'node_modules' folder does not exist in the Magento root path."], $warnings);
    }

    public function testVerboseGruntfileSampleCopyReportsEachStep(): void
    {
        $this->givenFiles($this->fileDriver, [
            'package.json' => true,
            'Gruntfile.js' => false,
            'Gruntfile.js.sample' => true,
        ]);
        $this->fileDriver->method('isDirectory')->willReturn(true);
        $this->io->method('confirm')->willReturn(true);

        $successMessages = [];
        $this->io
            ->method('success')
            ->willReturnCallback(function (string $message) use (&$successMessages): void {
                $successMessages[] = $message;
            });

        $this->assertTrue($this->checker->checkDependencies($this->io, true));
        $this->assertSame(
            [
                "The 'package.json' file found.",
                "The 'node_modules' folder found.",
                "The 'Gruntfile.js.sample' file found.",
                "'Gruntfile.js.sample' has been copied to 'Gruntfile.js'.",
            ],
            $successMessages,
        );
    }
}
