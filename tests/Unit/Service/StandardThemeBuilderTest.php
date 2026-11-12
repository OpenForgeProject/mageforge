<?php

declare(strict_types=1);

namespace OpenForgeProject\MageForge\Test\Unit\Service;

use OpenForgeProject\MageForge\Service\DependencyChecker;
use OpenForgeProject\MageForge\Service\GruntTaskRunner;
use OpenForgeProject\MageForge\Service\StandardThemeBuilder;
use OpenForgeProject\MageForge\Service\StaticContentDeployer;
use PHPUnit\Framework\MockObject\Stub;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

class StandardThemeBuilderTest extends TestCase
{
    /**
     * @var DependencyChecker&Stub
     */
    private $dependencyChecker;
    /**
     * @var GruntTaskRunner&Stub
     */
    private $gruntTaskRunner;
    /**
     * @var StaticContentDeployer&Stub
     */
    private $staticContentDeployer;
    /**
     * @var SymfonyStyle&Stub
     */
    private $io;
    /**
     * @var OutputInterface&Stub
     */
    private $output;
    /**
     * @var StandardThemeBuilder
     */
    private StandardThemeBuilder $builder;

    protected function setUp(): void
    {
        $this->dependencyChecker = $this->createStub(DependencyChecker::class);
        $this->gruntTaskRunner = $this->createStub(GruntTaskRunner::class);
        $this->staticContentDeployer = $this->createStub(StaticContentDeployer::class);
        $this->io = $this->createStub(SymfonyStyle::class);
        $this->output = $this->createStub(OutputInterface::class);
        $this->builder = $this->createBuilder();
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
        $gruntTaskRunner = $this->createMock(GruntTaskRunner::class);
        $gruntTaskRunner->expects($this->once())->method('runTasks')->willReturn(true);
        $this->staticContentDeployer->method('deploy')->willReturn(true);
        $builder = $this->createBuilder(gruntTaskRunner: $gruntTaskRunner);

        $successList = [];
        $this->assertTrue($builder->build('Vendor/one', $this->io, $this->output, false, $successList));
        $this->assertTrue($builder->build('Vendor/two', $this->io, $this->output, false, $successList));

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
        $gruntTaskRunner = $this->createMock(GruntTaskRunner::class);
        $gruntTaskRunner->expects($this->never())->method('runTasks');
        $staticContentDeployer = $this->createMock(StaticContentDeployer::class);
        $staticContentDeployer->expects($this->never())->method('deploy');
        $builder = $this->createBuilder(
            gruntTaskRunner: $gruntTaskRunner,
            staticContentDeployer: $staticContentDeployer,
        );

        $successList = [];
        $this->assertFalse($builder->build('Vendor/theme', $this->io, $this->output, false, $successList));
        $this->assertSame([], $successList);
    }

    public function testStopsWhenGruntTasksFail(): void
    {
        $this->dependencyChecker->method('checkDependencies')->willReturn(true);
        $this->gruntTaskRunner->method('runTasks')->willReturn(false);
        $staticContentDeployer = $this->createMock(StaticContentDeployer::class);
        $staticContentDeployer->expects($this->never())->method('deploy');
        $builder = $this->createBuilder(staticContentDeployer: $staticContentDeployer);

        $successList = [];
        $this->assertFalse($builder->build('Vendor/theme', $this->io, $this->output, false, $successList));
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
        $io = $this->createMock(SymfonyStyle::class);
        $io
            ->expects($this->once())
            ->method('success')
            ->with('Theme Vendor/theme has been successfully built.');

        $successList = [];
        $this->assertTrue($this->builder->build('Vendor/theme', $io, $this->output, true, $successList));
    }

    private function createBuilder(
        ?DependencyChecker $dependencyChecker = null,
        ?GruntTaskRunner $gruntTaskRunner = null,
        ?StaticContentDeployer $staticContentDeployer = null,
    ): StandardThemeBuilder {
        return new StandardThemeBuilder(
            $dependencyChecker ?? $this->dependencyChecker,
            $gruntTaskRunner ?? $this->gruntTaskRunner,
            $staticContentDeployer ?? $this->staticContentDeployer,
        );
    }
}
