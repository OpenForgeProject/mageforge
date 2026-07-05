<?php

declare(strict_types=1);

namespace OpenForgeProject\MageForge\Test\Unit\Service;

use Magento\Framework\Filesystem\Driver\File;
use Magento\Framework\Shell;
use OpenForgeProject\MageForge\Service\DependencyChecker;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Console\Style\SymfonyStyle;

class DependencyCheckerTest extends TestCase
{
    private File&MockObject $fileDriver;
    private Shell&MockObject $shell;
    private SymfonyStyle&MockObject $io;
    private DependencyChecker $checker;

    protected function setUp(): void
    {
        $this->fileDriver = $this->createMock(File::class);
        $this->shell = $this->createMock(Shell::class);
        $this->io = $this->createMock(SymfonyStyle::class);
        $this->checker = new DependencyChecker($this->fileDriver, $this->shell);
    }

    public function testSucceedsWhenAllDependenciesArePresent(): void
    {
        $this->givenFiles(['package.json' => true, 'Gruntfile.js' => true]);
        $this->fileDriver->method('isDirectory')->with('node_modules')->willReturn(true);
        $this->io->expects($this->never())->method('error');
        $this->io->expects($this->never())->method('success');
        $this->io->expects($this->never())->method('warning');

        $this->assertTrue($this->checker->checkDependencies($this->io, false));
    }

    public function testReportsEveryFoundDependencyInVerboseMode(): void
    {
        $this->givenFiles(['package.json' => true, 'Gruntfile.js' => true]);
        $this->fileDriver->method('isDirectory')->with('node_modules')->willReturn(true);

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
                "The 'Gruntfile.js' file found.",
            ],
            $successMessages,
        );
    }

    public function testWarnsAboutMissingFilesInVerboseMode(): void
    {
        $this->givenFiles(['package.json' => false, 'package.json.sample' => false]);

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
        $this->givenFiles(['package.json' => false, 'package.json.sample' => false]);
        $this->io->expects($this->once())->method('error')->with('Skipping this theme build.');

        $this->assertFalse($this->checker->checkDependencies($this->io, false));
    }

    public function testCopiesPackageJsonFromSampleWhenConfirmed(): void
    {
        $this->givenFiles([
            'package.json' => false,
            'package.json.sample' => true,
            'Gruntfile.js' => true,
        ]);
        $this->fileDriver->method('isDirectory')->willReturn(true);
        $this->io->method('confirm')->willReturn(true);
        $this->fileDriver
            ->expects($this->once())
            ->method('copy')
            ->with('package.json.sample', 'package.json');

        $this->assertTrue($this->checker->checkDependencies($this->io, false));
    }

    public function testRunsNpmInstallWhenNodeModulesMissingAndConfirmed(): void
    {
        $this->givenFiles(['package.json' => true, 'Gruntfile.js' => true]);
        $this->fileDriver->method('isDirectory')->willReturn(false);
        $this->io->method('confirm')->willReturn(true);
        $this->shell->expects($this->once())->method('execute')->with('npm install --quiet');

        $this->assertTrue($this->checker->checkDependencies($this->io, false));
    }

    public function testFailsWhenNpmInstallFails(): void
    {
        $this->givenFiles(['package.json' => true]);
        $this->fileDriver->method('isDirectory')->willReturn(false);
        $this->io->method('confirm')->willReturn(true);
        $this->shell->method('execute')->willThrowException(new \RuntimeException('npm error'));
        $this->io->expects($this->once())->method('error')->with('npm error');

        $this->assertFalse($this->checker->checkDependencies($this->io, false));
    }

    public function testFailsWhenNpmInstallIsDeclined(): void
    {
        $this->givenFiles(['package.json' => true]);
        $this->fileDriver->method('isDirectory')->willReturn(false);
        $this->io->method('confirm')->willReturn(false);
        $this->shell->expects($this->never())->method('execute');
        $this->io->expects($this->once())->method('error')->with('Skipping this theme build.');

        $this->assertFalse($this->checker->checkDependencies($this->io, false));
    }

    public function testFailsWhenGruntfileAndSampleAreMissing(): void
    {
        $this->givenFiles([
            'package.json' => true,
            'Gruntfile.js' => false,
            'Gruntfile.js.sample' => false,
        ]);
        $this->fileDriver->method('isDirectory')->willReturn(true);
        $this->io->expects($this->once())->method('error')->with('Skipping this theme build.');

        $this->assertFalse($this->checker->checkDependencies($this->io, false));
    }

    public function testCopiesGruntfileFromSampleWhenConfirmed(): void
    {
        $this->givenFiles([
            'package.json' => true,
            'Gruntfile.js' => false,
            'Gruntfile.js.sample' => true,
        ]);
        $this->fileDriver->method('isDirectory')->willReturn(true);
        $this->io->method('confirm')->willReturn(true);
        $this->fileDriver
            ->expects($this->once())
            ->method('copy')
            ->with('Gruntfile.js.sample', 'Gruntfile.js');

        $this->assertTrue($this->checker->checkDependencies($this->io, false));
    }

    public function testSkippingSampleCopyStillSucceeds(): void
    {
        $this->givenFiles([
            'package.json' => false,
            'package.json.sample' => true,
            'Gruntfile.js' => true,
        ]);
        $this->fileDriver->method('isDirectory')->willReturn(true);
        $this->io->method('confirm')->willReturn(false);
        $this->fileDriver->expects($this->never())->method('copy');

        $this->assertTrue($this->checker->checkDependencies($this->io, false));
    }

    /**
     * Configure the file driver mock; unlisted files do not exist.
     *
     * @param array<string, bool> $files
     */
    private function givenFiles(array $files): void
    {
        $this->fileDriver
            ->method('isFile')
            ->willReturnCallback(static fn(string $path): bool => $files[$path] ?? false);
    }

    public function testVerboseSampleCopyFlowReportsEachStep(): void
    {
        $this->givenFiles([
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
        $this->givenFiles(['package.json' => true, 'Gruntfile.js' => true]);
        $this->fileDriver->method('isDirectory')->willReturn(false);
        $this->io->method('confirm')->willReturn(true);
        $this->shell->method('execute')->with('npm install --quiet')->willReturn('added 120 packages');
        $this->io->expects($this->once())->method('section')->with("Running 'npm install'... Please wait.");
        $this->io->expects($this->once())->method('writeln')->with('added 120 packages');

        $warnings = [];
        $this->io
            ->method('warning')
            ->willReturnCallback(function (string $message) use (&$warnings): void {
                $warnings[] = $message;
            });

        $this->assertTrue($this->checker->checkDependencies($this->io, true));
        $this->assertSame(["The 'node_modules' folder does not exist in the Magento root path."], $warnings);
    }

    public function testVerboseGruntfileSampleCopyReportsEachStep(): void
    {
        $this->givenFiles([
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
