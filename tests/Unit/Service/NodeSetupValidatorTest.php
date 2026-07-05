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
    private FileDriver&MockObject $fileDriver;
    private NodePackageManager&MockObject $nodePackageManager;
    private SymfonyStyle&MockObject $io;
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

        $result = $this->validator->validateAndRestore('.', $this->io, true);

        $this->assertTrue($result);
        $this->nodePackageManager->expects($this->never())->method('installNodeModules');
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
}
