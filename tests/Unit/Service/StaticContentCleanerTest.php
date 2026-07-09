<?php

declare(strict_types=1);

namespace OpenForgeProject\MageForge\Test\Unit\Service;

use Magento\Framework\App\State;
use OpenForgeProject\MageForge\Service\StaticContentCleaner;
use OpenForgeProject\MageForge\Service\ThemeCleaner;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

class StaticContentCleanerTest extends TestCase
{
    /**
     * @var State&MockObject
     */
    private $state;
    /**
     * @var ThemeCleaner&MockObject
     */
    private $themeCleaner;
    /**
     * @var SymfonyStyle&MockObject
     */
    private $io;
    /**
     * @var OutputInterface&MockObject
     */
    private $output;
    /**
     * @var StaticContentCleaner
     */
    private StaticContentCleaner $cleaner;

    protected function setUp(): void
    {
        $this->state = $this->createMock(State::class);
        $this->themeCleaner = $this->createMock(ThemeCleaner::class);
        $this->io = $this->createMock(SymfonyStyle::class);
        $this->output = $this->createMock(OutputInterface::class);
        $this->cleaner = new StaticContentCleaner($this->state, $this->themeCleaner);
    }

    public function testDoesNothingOutsideDeveloperMode(): void
    {
        $this->state->method('getMode')->willReturn(State::MODE_PRODUCTION);
        $this->themeCleaner->expects($this->never())->method('hasStaticFiles');

        $this->assertTrue($this->cleaner->cleanIfNeeded('Vendor/theme', $this->io, $this->output, false));
    }

    public function testDoesNothingWhenNoStaticFilesExist(): void
    {
        $this->state->method('getMode')->willReturn(State::MODE_DEVELOPER);
        $this->themeCleaner->method('hasStaticFiles')->with('Vendor/theme')->willReturn(false);
        $this->themeCleaner->expects($this->never())->method('cleanPubStatic');

        $this->assertTrue($this->cleaner->cleanIfNeeded('Vendor/theme', $this->io, $this->output, false));
    }

    public function testCleansStaticAndPreprocessedFilesInDeveloperMode(): void
    {
        $this->state->method('getMode')->willReturn(State::MODE_DEVELOPER);
        $this->themeCleaner->method('hasStaticFiles')->willReturn(true);
        $this->themeCleaner
            ->expects($this->once())
            ->method('cleanPubStatic')
            ->with('Vendor/theme', $this->io, false, false)
            ->willReturn(3);
        $this->themeCleaner
            ->expects($this->once())
            ->method('cleanViewPreprocessed')
            ->with('Vendor/theme', $this->io, false, false)
            ->willReturn(1);

        $this->assertTrue($this->cleaner->cleanIfNeeded('Vendor/theme', $this->io, $this->output, false));
    }

    public function testReturnsFalseWhenNothingWasCleaned(): void
    {
        $this->state->method('getMode')->willReturn(State::MODE_DEVELOPER);
        $this->themeCleaner->method('hasStaticFiles')->willReturn(true);
        $this->themeCleaner->method('cleanPubStatic')->willReturn(0);
        $this->themeCleaner->method('cleanViewPreprocessed')->willReturn(0);

        $this->assertFalse($this->cleaner->cleanIfNeeded('Vendor/theme', $this->io, $this->output, false));
    }

    public function testNotifiesUserInVerboseMode(): void
    {
        $this->state->method('getMode')->willReturn(State::MODE_DEVELOPER);
        $this->themeCleaner->method('hasStaticFiles')->willReturn(true);
        $this->themeCleaner->method('cleanPubStatic')->willReturn(1);
        $this->themeCleaner->method('cleanViewPreprocessed')->willReturn(0);
        $this->io
            ->expects($this->once())
            ->method('note')
            ->with("Developer mode detected: Cleaning existing static files for theme 'Vendor/theme'...");

        $this->assertTrue($this->cleaner->cleanIfNeeded('Vendor/theme', $this->io, $this->output, true));
    }

    public function testReturnsFalseAndPrintsErrorOnException(): void
    {
        $this->state->method('getMode')->willThrowException(new \RuntimeException('state unavailable'));
        $this->io
            ->expects($this->once())
            ->method('error')
            ->with('Failed to check/clean static content: state unavailable');

        $this->assertFalse($this->cleaner->cleanIfNeeded('Vendor/theme', $this->io, $this->output, false));
    }
}
