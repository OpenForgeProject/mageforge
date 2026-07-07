<?php

declare(strict_types=1);

namespace OpenForgeProject\MageForge\Test\Unit\Service\TemplateOverride;

use Magento\Framework\Filesystem\Driver\File;
use OpenForgeProject\MageForge\Service\TemplateOverride\TemplateCopier;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

class TemplateCopierTest extends TestCase
{
    private File&MockObject $fileDriver;
    private TemplateCopier $copier;

    protected function setUp(): void
    {
        $this->fileDriver = $this->createMock(File::class);
        $this->copier = new TemplateCopier($this->fileDriver);
    }

    public function testCreatesMissingTargetDirectoryBeforeCopying(): void
    {
        $this->fileDriver->method('getParentDirectory')->with('/theme/M/templates/a/b.phtml')
            ->willReturn('/theme/M/templates/a');
        $this->fileDriver->method('isDirectory')->with('/theme/M/templates/a')->willReturn(false);
        $this->fileDriver->expects($this->once())->method('createDirectory')->with('/theme/M/templates/a');
        $this->fileDriver
            ->expects($this->once())
            ->method('copy')
            ->with('/module/templates/a/b.phtml', '/theme/M/templates/a/b.phtml')
            ->willReturn(true);

        $this->copier->copy('/module/templates/a/b.phtml', '/theme/M/templates/a/b.phtml');
    }

    public function testDoesNotCreateExistingTargetDirectory(): void
    {
        $this->fileDriver->method('getParentDirectory')->willReturn('/theme/M/templates');
        $this->fileDriver->method('isDirectory')->willReturn(true);
        $this->fileDriver->expects($this->never())->method('createDirectory');
        $this->fileDriver->expects($this->once())->method('copy')->willReturn(true);

        $this->copier->copy('/source.phtml', '/theme/M/templates/target.phtml');
    }
}
