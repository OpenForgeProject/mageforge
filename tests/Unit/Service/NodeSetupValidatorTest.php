<?php

declare(strict_types=1);

namespace OpenForgeProject\MageForge\Test\Unit\Service;

use Magento\Framework\Filesystem\Driver\File as FileDriver;
use OpenForgeProject\MageForge\Service\NodePackageManager;
use OpenForgeProject\MageForge\Service\NodeSetupValidator;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Console\Style\SymfonyStyle;

class NodeSetupValidatorTest extends TestCase
{
    /**
     * @var FileDriver&MockObject
     */
    private $fileDriver;
    /**
     * @var NodePackageManager&MockObject
     */
    private $nodePackageManager;
    /**
     * @var SymfonyStyle&MockObject
     */
    private $io;
    /**
     * @var NodeSetupValidator
     */
    private NodeSetupValidator $validator;

    protected function setUp(): void
    {
        $this->fileDriver = $this->createMock(FileDriver::class);
        $this->nodePackageManager = $this->createMock(NodePackageManager::class);
        $this->io = $this->createMock(SymfonyStyle::class);

        $this->validator = new NodeSetupValidator($this->fileDriver, $this->nodePackageManager);
    }

    private function markAllRequiredFilesPresent(): void
    {
        // Exact-path whitelists (rather than a blanket willReturn(true)) so that a mutation to
        // the path concatenation (wrong separator, wrong operand order, etc.) produces a path
        // that doesn't match and is treated as "missing", changing the test's outcome.
        $expectedFiles = ['./package.json', './Gruntfile.js', './grunt-config.json', './package-lock.json'];
        $this->fileDriver->method('isExists')->willReturnCallback(
            static fn (string $path): bool => in_array($path, $expectedFiles, true),
        );
        $this->fileDriver->method('isDirectory')->willReturnCallback(
            static fn (string $path): bool => $path === './node_modules',
        );
    }

    public function testValidateAndRestoreReturnsTrueWhenNothingIsMissing(): void
    {
        $this->markAllRequiredFilesPresent();

        $this->assertTrue($this->validator->validateAndRestore('.', $this->io, false));
    }

    public function testValidateAndRestoreReportsSuccessInVerboseModeWhenNothingMissing(): void
    {
        $this->markAllRequiredFilesPresent();
        $this->io->expects($this->once())->method('success')
            ->with('All required Node.js setup files are present.');

        $this->assertTrue($this->validator->validateAndRestore('.', $this->io, true));
    }

    public function testValidateAndRestoreAutomaticallyRestoresOnlyMissingGeneratedFiles(): void
    {
        // Everything present except the generated package-lock.json file. Generated files are
        // never copied from Magento base (they're only ever produced by npm install), so no
        // copy() call is expected here.
        $expectedFiles = ['./package.json', './Gruntfile.js', './grunt-config.json'];
        $this->fileDriver->method('isExists')->willReturnCallback(
            static fn (string $path): bool => in_array($path, $expectedFiles, true),
        );
        $this->fileDriver->method('isDirectory')->willReturnCallback(
            static fn (string $path): bool => $path === './node_modules' || $path === 'vendor/magento/magento2-base',
        );
        $this->fileDriver->expects($this->never())->method('copy');
        $this->nodePackageManager->expects($this->never())->method('installNodeModules');

        $this->assertTrue($this->validator->validateAndRestore('.', $this->io, true));
    }

    public function testValidateAndRestoreInstallsNodeModulesWhenDirectoryMissing(): void
    {
        // Every required/generated file present, but node_modules directory missing.
        $expectedFiles = ['./package.json', './Gruntfile.js', './grunt-config.json', './package-lock.json'];
        $this->fileDriver->method('isExists')->willReturnCallback(
            static fn (string $path): bool => in_array($path, $expectedFiles, true),
        );
        $this->fileDriver->method('isDirectory')->willReturnCallback(
            static fn (string $path): bool => $path === 'vendor/magento/magento2-base',
        );
        $this->nodePackageManager->expects($this->once())
            ->method('installNodeModules')
            ->with('.', $this->io, false)
            ->willReturn(true);

        $result = $this->validator->validateAndRestore('.', $this->io, false);

        $this->assertTrue($result);
    }

    public function testValidateAndRestoreReturnsFalseWhenNpmInstallFails(): void
    {
        $expectedFiles = ['./package.json', './Gruntfile.js', './grunt-config.json', './package-lock.json'];
        $this->fileDriver->method('isExists')->willReturnCallback(
            static fn (string $path): bool => in_array($path, $expectedFiles, true),
        );
        $this->fileDriver->method('isDirectory')->willReturnCallback(
            static fn (string $path): bool => $path === 'vendor/magento/magento2-base',
        );
        $this->nodePackageManager->method('installNodeModules')->willReturn(false);

        $this->assertFalse($this->validator->validateAndRestore('.', $this->io, false));
    }

    // -------------------------------------------------------------------------
    // Mutation hardening: exact messages and flow boundaries
    // -------------------------------------------------------------------------

    public function testAllFilesPresentDoesNotEnterRestoreFlow(): void
    {
        $this->markAllRequiredFilesPresent();
        $this->io->expects($this->never())->method('note');
        $this->io->expects($this->never())->method('warning');
        $this->io->expects($this->once())->method('success')->with('All required Node.js setup files are present.');

        $this->assertTrue($this->validator->validateAndRestore('.', $this->io, true));
    }

    public function testVerboseAutoRestoreAnnouncesEachGeneratedItem(): void
    {
        // Only generated artifacts missing: no prompt, no source-file copies.
        $this->fileDriver->method('isExists')->willReturnCallback(
            static fn(string $path): bool => !str_ends_with($path, 'package-lock.json'),
        );
        $this->fileDriver->method('isDirectory')->willReturnCallback(
            static fn(string $path): bool => !str_ends_with($path, 'node_modules'),
        );
        $this->nodePackageManager->method('installNodeModules')->willReturn(true);
        $this->io->expects($this->never())->method('warning');
        $this->fileDriver->expects($this->never())->method('copy');

        $notes = [];
        $this->io
            ->method('note')
            ->willReturnCallback(function (string $message) use (&$notes): void {
                $notes[] = $message;
            });
        $lines = [];
        $this->io
            ->method('writeln')
            ->willReturnCallback(function (string $line) use (&$lines): void {
                $lines[] = $line;
            });
        $this->io->expects($this->once())->method('text')->with('Installing Node.js dependencies...');

        $this->assertTrue($this->validator->validateAndRestore('.', $this->io, true));
        $this->assertSame(
            [
                'Detected missing generated files/directories. Installing automatically...',
                'Skipping package-lock.json - will be generated by npm install',
                'Skipping node_modules/ - will be generated by npm install',
            ],
            $notes,
        );
        $this->assertSame(['  - package-lock.json', '  - node_modules/'], $lines);
    }

    public function testFailedNpmInstallReportsExactError(): void
    {
        $this->fileDriver->method('isExists')->willReturnCallback(
            static fn(string $path): bool => !str_ends_with($path, 'package-lock.json'),
        );
        $this->fileDriver->method('isDirectory')->willReturnCallback(
            static fn(string $path): bool => !str_ends_with($path, 'node_modules'),
        );
        $this->nodePackageManager->method('installNodeModules')->willReturn(false);
        $this->io->expects($this->once())->method('error')->with('Failed to install Node.js dependencies.');

        $this->assertFalse($this->validator->validateAndRestore('.', $this->io, false));
    }

    public function testQuietAutoRestoreStaysSilent(): void
    {
        $this->fileDriver->method('isExists')->willReturnCallback(
            static fn(string $path): bool => !str_ends_with($path, 'package-lock.json'),
        );
        $this->fileDriver->method('isDirectory')->willReturnCallback(
            static fn(string $path): bool => !str_ends_with($path, 'node_modules'),
        );
        $this->nodePackageManager->method('installNodeModules')->willReturn(true);
        $this->io->expects($this->never())->method('note');
        $this->io->expects($this->never())->method('writeln');

        $this->assertTrue($this->validator->validateAndRestore('.', $this->io, false));
    }
}
