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
        $this->fileDriver->method('isExists')->willReturn(true);
        $this->fileDriver->method('isDirectory')->willReturn(true);
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
        // Everything present except the generated package-lock.json file.
        $this->fileDriver->method('isExists')->willReturnCallback(
            static fn (string $path): bool => !str_ends_with($path, 'package-lock.json'),
        );
        $this->fileDriver->method('isDirectory')->willReturn(true);
        $this->fileDriver->method('copy')->willReturn(true);

        $result = $this->validator->validateAndRestore('.', $this->io, true);

        $this->assertTrue($result);
        $this->nodePackageManager->expects($this->never())->method('installNodeModules');
    }

    public function testValidateAndRestoreInstallsNodeModulesWhenDirectoryMissing(): void
    {
        // Every required/generated file present, but node_modules directory missing.
        $this->fileDriver->method('isExists')->willReturn(true);
        $this->fileDriver->method('isDirectory')->willReturnCallback(
            static fn (string $path): bool => !str_ends_with(rtrim($path, '/'), 'node_modules'),
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
        $this->fileDriver->method('isExists')->willReturn(true);
        $this->fileDriver->method('isDirectory')->willReturnCallback(
            static fn (string $path): bool => !str_ends_with(rtrim($path, '/'), 'node_modules'),
        );
        $this->nodePackageManager->method('installNodeModules')->willReturn(false);

        $this->assertFalse($this->validator->validateAndRestore('.', $this->io, false));
    }
}
