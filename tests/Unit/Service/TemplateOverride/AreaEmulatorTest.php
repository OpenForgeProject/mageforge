<?php

declare(strict_types=1);

namespace OpenForgeProject\MageForge\Test\Unit\Service\TemplateOverride;

use Magento\Framework\App\State;
use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\ObjectManager\ConfigLoaderInterface;
use Magento\Framework\ObjectManagerInterface;
use OpenForgeProject\MageForge\Service\TemplateOverride\AreaEmulator;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

class AreaEmulatorTest extends TestCase
{
    private State&MockObject $appState;
    private ConfigLoaderInterface&MockObject $configLoader;
    private ObjectManagerInterface&MockObject $objectManager;
    private AreaEmulator $areaEmulator;

    protected function setUp(): void
    {
        $this->appState = $this->createMock(State::class);
        $this->configLoader = $this->createMock(ConfigLoaderInterface::class);
        $this->objectManager = $this->createMock(ObjectManagerInterface::class);
        $this->areaEmulator = new AreaEmulator($this->appState, $this->configLoader, $this->objectManager);
    }

    public function testSetsAreaCodeAndLoadsDiConfiguration(): void
    {
        $diConfig = ['preferences' => ['SomeInterface' => 'SomeClass']];
        $this->appState
            ->method('getAreaCode')
            ->willThrowException(new LocalizedException(__('Area code is not set')));
        $this->appState->expects($this->once())->method('setAreaCode')->with('frontend');
        $this->configLoader->expects($this->once())->method('load')->with('frontend')->willReturn($diConfig);
        $this->objectManager->expects($this->once())->method('configure')->with($diConfig);

        $this->areaEmulator->emulate('frontend');
    }

    public function testLoadsDiConfigurationEvenWhenAreaCodeIsAlreadySet(): void
    {
        $this->appState->method('getAreaCode')->willReturn('adminhtml');
        $this->appState->expects($this->never())->method('setAreaCode');
        $this->configLoader->method('load')->with('frontend')->willReturn([]);
        $this->objectManager->expects($this->once())->method('configure')->with([]);

        $this->areaEmulator->emulate('frontend');
    }
}
