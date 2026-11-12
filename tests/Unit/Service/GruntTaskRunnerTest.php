<?php

declare(strict_types=1);

namespace OpenForgeProject\MageForge\Test\Unit\Service;

use Magento\Framework\Shell;
use OpenForgeProject\MageForge\Service\GruntTaskRunner;
use PHPUnit\Framework\MockObject\Stub;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

class GruntTaskRunnerTest extends TestCase
{
    /**
     * @var Shell&Stub
     */
    private $shell;
    /**
     * @var SymfonyStyle&Stub
     */
    private $io;
    /**
     * @var OutputInterface&Stub
     */
    private $output;
    /**
     * @var GruntTaskRunner
     */
    private GruntTaskRunner $taskRunner;

    protected function setUp(): void
    {
        $this->shell = $this->createStub(Shell::class);
        $this->io = $this->createStub(SymfonyStyle::class);
        $this->output = $this->createStub(OutputInterface::class);
        $this->taskRunner = new GruntTaskRunner($this->shell);
    }

    public function testRunsCleanAndLessQuietlyWhenNotVerbose(): void
    {
        $executedCommands = [];
        $this->shell
            ->method('execute')
            ->willReturnCallback(function (string $command) use (&$executedCommands): string {
                $executedCommands[] = $command;
                return '';
            });
        $output = $this->createMock(OutputInterface::class);
        $output->expects($this->never())->method('writeln');

        $this->assertTrue($this->taskRunner->runTasks($this->io, $output, false));
        $this->assertSame(
            ['node_modules/.bin/grunt clean --quiet', 'node_modules/.bin/grunt less --quiet'],
            $executedCommands,
        );
    }

    public function testRunsTasksAndForwardsOutputWhenVerbose(): void
    {
        $this->shell
            ->method('execute')
            ->willReturnMap([
                ['node_modules/.bin/grunt clean', [], 'clean done'],
                ['node_modules/.bin/grunt less', [], 'less done'],
            ]);

        $forwarded = [];
        $this->output
            ->method('writeln')
            ->willReturnCallback(function (string $line) use (&$forwarded): void {
                $forwarded[] = $line;
            });
        $announced = [];
        $io = $this->createMock(SymfonyStyle::class);
        $io
            ->method('text')
            ->willReturnCallback(function (string $message) use (&$announced): void {
                $announced[] = $message;
            });
        $io->expects($this->once())->method('success')->with('Grunt tasks completed successfully.');

        $this->assertTrue($this->taskRunner->runTasks($io, $this->output, true));
        $this->assertSame(['clean done', 'less done'], $forwarded);
        $this->assertSame(['Running grunt clean...', 'Running grunt less...'], $announced);
    }

    public function testReturnsFalseAndPrintsErrorWhenGruntFails(): void
    {
        $this->shell->method('execute')->willThrowException(new \RuntimeException('grunt not found'));
        $io = $this->createMock(SymfonyStyle::class);
        $io->expects($this->once())->method('error')->with('Failed to run grunt tasks: grunt not found');

        $this->assertFalse($this->taskRunner->runTasks($io, $this->output, false));
    }
}
