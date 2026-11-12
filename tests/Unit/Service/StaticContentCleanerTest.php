<?php

declare(strict_types=1);

namespace OpenForgeProject\MageForge\Test\Unit\Service;

use Magento\Framework\App\State;
use OpenForgeProject\MageForge\Service\StaticContentCleaner;
use OpenForgeProject\MageForge\Service\ThemeCleaner;
use PHPUnit\Framework\MockObject\Stub;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

class StaticContentCleanerTest extends TestCase
{
    /**
     * @var State&Stub
     */
    private $state;
    /**
     * @var ThemeCleaner&Stub
     */
    private $themeCleaner;
    /**
     * @var SymfonyStyle&Stub
     */
    private $io;
    /**
     * @var OutputInterface&Stub
     */
    private $output;
    /**
     * @var StaticContentCleaner
     */
    private StaticContentCleaner $cleaner;

    protected function setUp(): void
    {
        $this->state = $this->createStub(State::class);
        $this->themeCleaner = $this->createStub(ThemeCleaner::class);
        $this->io = $this->createStub(SymfonyStyle::class);
        $this->output = $this->createStub(OutputInterface::class);
        $this->cleaner = $this->createCleaner();
    }

    public function testDoesNothingOutsideDeveloperMode(): void
    {
        $this->state->method('getMode')->willReturn(State::MODE_PRODUCTION);
        $themeCleaner = $this->createMock(ThemeCleaner::class);
        $themeCleaner->expects($this->never())->method('hasStaticFiles');

        $cleaner = $this->createCleaner(themeCleaner: $themeCleaner);

        $this->assertTrue($cleaner->cleanIfNeeded('Vendor/theme', $this->io, $this->output, false));
    }

    public function testDoesNothingWhenNoStaticFilesExist(): void
    {
        $this->state->method('getMode')->willReturn(State::MODE_DEVELOPER);
        $themeCleaner = $this->createMock(ThemeCleaner::class);
        $themeCleaner->expects($this->once())->method('hasStaticFiles')->with('Vendor/theme')->willReturn(false);
        $themeCleaner->expects($this->never())->method('cleanPubStatic');

        $cleaner = $this->createCleaner(themeCleaner: $themeCleaner);

        $this->assertTrue($cleaner->cleanIfNeeded('Vendor/theme', $this->io, $this->output, false));
    }

    public function testCleansStaticAndPreprocessedFilesInDeveloperMode(): void
    {
        $this->state->method('getMode')->willReturn(State::MODE_DEVELOPER);
        $themeCleaner = $this->createMock(ThemeCleaner::class);
        $themeCleaner->method('hasStaticFiles')->willReturn(true);
        $themeCleaner
            ->expects($this->once())
            ->method('cleanPubStatic')
            ->with('Vendor/theme', $this->io, false, false)
            ->willReturn(3);
        $themeCleaner
            ->expects($this->once())
            ->method('cleanViewPreprocessed')
            ->with('Vendor/theme', $this->io, false, false)
            ->willReturn(1);

        $cleaner = $this->createCleaner(themeCleaner: $themeCleaner);

        $this->assertTrue($cleaner->cleanIfNeeded('Vendor/theme', $this->io, $this->output, false));
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
        $io = $this->createMock(SymfonyStyle::class);
        $io
            ->expects($this->once())
            ->method('note')
            ->with("Developer mode detected: Cleaning existing static files for theme 'Vendor/theme'...");

        $this->assertTrue($this->cleaner->cleanIfNeeded('Vendor/theme', $io, $this->output, true));
    }

    public function testReturnsFalseAndPrintsErrorOnException(): void
    {
        $this->state->method('getMode')->willThrowException(new \RuntimeException('state unavailable'));
        $io = $this->createMock(SymfonyStyle::class);
        $io
            ->expects($this->once())
            ->method('error')
            ->with('Failed to check/clean static content: state unavailable');

        $this->assertFalse($this->cleaner->cleanIfNeeded('Vendor/theme', $io, $this->output, false));
    }

    private function createCleaner(?State $state = null, ?ThemeCleaner $themeCleaner = null): StaticContentCleaner
    {
        return new StaticContentCleaner($state ?? $this->state, $themeCleaner ?? $this->themeCleaner);
    }
}
