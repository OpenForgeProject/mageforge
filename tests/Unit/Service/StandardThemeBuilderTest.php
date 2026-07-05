<?php

declare(strict_types=1);

namespace OpenForgeProject\MageForge\Test\Unit\Service;

use OpenForgeProject\MageForge\Service\DependencyChecker;
use OpenForgeProject\MageForge\Service\GruntTaskRunner;
use OpenForgeProject\MageForge\Service\StandardThemeBuilder;
use OpenForgeProject\MageForge\Service\StaticContentDeployer;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

class StandardThemeBuilderTest extends TestCase
{
    private DependencyChecker&MockObject $dependencyChecker;
    private GruntTaskRunner&MockObject $gruntTaskRunner;
    private StaticContentDeployer&MockObject $staticContentDeployer;
    private SymfonyStyle&MockObject $io;
    private OutputInterface&MockObject $output;
    private StandardThemeBuilder $builder;

    protected function setUp(): void
    {
        $this->dependencyChecker = $this->createMock(DependencyChecker::class);
        $this->gruntTaskRunner = $this->createMock(GruntTaskRunner::class);
        $this->staticContentDeployer = $this->createMock(StaticContentDeployer::class);
        $this->io = $this->createMock(SymfonyStyle::class);
        $this->output = $this->createMock(OutputInterface::class);
        $this->builder = new StandardThemeBuilder(
            $this->dependencyChecker,
            $this->gruntTaskRunner,
            $this->staticContentDeployer,
        );
    }

    public function testBuildsThemeAndRecordsAllSteps(): void
    {
        $this->dependencyChecker->method('checkDependencies')->willReturn(true);
        $this->gruntTaskRunner->method('runTasks')->willReturn(true);
        $this->staticContentDeployer->method('deploy')->willReturn(true);

        $successList = [];
        $this->assertTrue($this->builder->build('Vendor/theme', $this->io, $this->output, false, $successList));
        $this->assertSame(
            [
                'Vendor/theme: Dependencies checked',
                'Global: Grunt tasks executed',
                'Vendor/theme: Static content deployed',
            ],
            $successList,
        );
    }

    public function testRunsGruntTasksOnlyOncePerBuildProcess(): void
    {
        $this->dependencyChecker->method('checkDependencies')->willReturn(true);
        $this->gruntTaskRunner->expects($this->once())->method('runTasks')->willReturn(true);
        $this->staticContentDeployer->method('deploy')->willReturn(true);

        $successList = [];
        $this->assertTrue($this->builder->build('Vendor/one', $this->io, $this->output, false, $successList));
        $this->assertTrue($this->builder->build('Vendor/two', $this->io, $this->output, false, $successList));

        $this->assertSame(
            [
                'Vendor/one: Dependencies checked',
                'Global: Grunt tasks executed',
                'Vendor/one: Static content deployed',
                'Vendor/two: Dependencies checked',
                'Vendor/two: Static content deployed',
            ],
            $successList,
        );
    }

    public function testStopsWhenDependencyCheckFails(): void
    {
        $this->dependencyChecker->method('checkDependencies')->willReturn(false);
        $this->gruntTaskRunner->expects($this->never())->method('runTasks');
        $this->staticContentDeployer->expects($this->never())->method('deploy');

        $successList = [];
        $this->assertFalse($this->builder->build('Vendor/theme', $this->io, $this->output, false, $successList));
        $this->assertSame([], $successList);
    }

    public function testStopsWhenGruntTasksFail(): void
    {
        $this->dependencyChecker->method('checkDependencies')->willReturn(true);
        $this->gruntTaskRunner->method('runTasks')->willReturn(false);
        $this->staticContentDeployer->expects($this->never())->method('deploy');

        $successList = [];
        $this->assertFalse($this->builder->build('Vendor/theme', $this->io, $this->output, false, $successList));
        $this->assertSame(['Vendor/theme: Dependencies checked'], $successList);
    }

    public function testStopsWhenStaticContentDeploymentFails(): void
    {
        $this->dependencyChecker->method('checkDependencies')->willReturn(true);
        $this->gruntTaskRunner->method('runTasks')->willReturn(true);
        $this->staticContentDeployer->method('deploy')->willReturn(false);

        $successList = [];
        $this->assertFalse($this->builder->build('Vendor/theme', $this->io, $this->output, false, $successList));
    }

    public function testReportsSuccessInVerboseMode(): void
    {
        $this->dependencyChecker->method('checkDependencies')->willReturn(true);
        $this->gruntTaskRunner->method('runTasks')->willReturn(true);
        $this->staticContentDeployer->method('deploy')->willReturn(true);
        $this->io
            ->expects($this->once())
            ->method('success')
            ->with('Theme Vendor/theme has been successfully built.');

        $successList = [];
        $this->assertTrue($this->builder->build('Vendor/theme', $this->io, $this->output, true, $successList));
    }
}
