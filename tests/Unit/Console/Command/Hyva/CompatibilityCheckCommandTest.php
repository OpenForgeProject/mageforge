<?php

declare(strict_types=1);

namespace OpenForgeProject\MageForge\Test\Unit\Console\Command\Hyva;

use Magento\Framework\Console\Cli;
use OpenForgeProject\MageForge\Console\Command\Hyva\CompatibilityCheckCommand;
use OpenForgeProject\MageForge\Service\Hyva\CompatibilityChecker;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Console\Tester\CommandTester;

class CompatibilityCheckCommandTest extends TestCase
{
    private CompatibilityChecker&MockObject $compatibilityChecker;
    private CompatibilityCheckCommand $command;

    protected function setUp(): void
    {
        $this->compatibilityChecker = $this->createMock(CompatibilityChecker::class);
        $this->command = new CompatibilityCheckCommand($this->compatibilityChecker);
    }

    /**
     * @param array<string, mixed> $overrides
     * @return array<string, mixed>
     */
    private function makeResults(array $overrides = []): array
    {
        return array_merge([
            'modules' => [],
            'summary' => [
                'total' => 3,
                'compatible' => 3,
                'incompatible' => 0,
                'hyvaAware' => 0,
                'criticalIssues' => 0,
                'warningIssues' => 0,
            ],
            'hasIncompatibilities' => false,
        ], $overrides);
    }

    public function testReturnsSuccessAndReportsAllCompatible(): void
    {
        $this->compatibilityChecker->method('check')->willReturn($this->makeResults());
        $this->compatibilityChecker->method('formatResultsForDisplay')->willReturn([]);

        $tester = new CommandTester($this->command);
        $exitCode = $tester->execute([]);

        $this->assertSame(Cli::RETURN_SUCCESS, $exitCode);
        $this->assertStringContainsString('All scanned modules are compatible with Hyv', $tester->getDisplay());
    }

    public function testReturnsFailureWhenCriticalIssuesFound(): void
    {
        $results = $this->makeResults([
            'summary' => [
                'total' => 5,
                'compatible' => 3,
                'incompatible' => 2,
                'hyvaAware' => 0,
                'criticalIssues' => 2,
                'warningIssues' => 1,
            ],
            'hasIncompatibilities' => true,
        ]);
        $this->compatibilityChecker->method('check')->willReturn($results);
        $this->compatibilityChecker->method('formatResultsForDisplay')
            ->willReturn([['Vendor_Module', 'Incompatible', '2 critical']]);

        $tester = new CommandTester($this->command);
        $exitCode = $tester->execute([]);

        $this->assertSame(Cli::RETURN_FAILURE, $exitCode);
        $display = $tester->getDisplay();
        $this->assertStringContainsString('critical compatibility issue', $display);
        $this->assertStringContainsString('Recommendations', $display);
    }

    public function testReturnsSuccessWithWarningsOnly(): void
    {
        $results = $this->makeResults([
            'summary' => [
                'total' => 4,
                'compatible' => 3,
                'incompatible' => 1,
                'hyvaAware' => 0,
                'criticalIssues' => 0,
                'warningIssues' => 2,
            ],
            'hasIncompatibilities' => true,
        ]);
        $this->compatibilityChecker->method('check')->willReturn($results);
        $this->compatibilityChecker->method('formatResultsForDisplay')
            ->willReturn([['Vendor_Module', 'Warning', '2 warnings']]);

        $tester = new CommandTester($this->command);
        $exitCode = $tester->execute([]);

        $this->assertSame(Cli::RETURN_SUCCESS, $exitCode);
        $this->assertStringContainsString('warning(s)', $tester->getDisplay());
    }

    public function testDetailedOptionDisplaysFileLevelIssues(): void
    {
        $moduleData = [
            'compatible' => false,
            'hasWarnings' => false,
            'scanResult' => ['files' => [], 'criticalIssues' => 1, 'totalIssues' => 1],
            'moduleInfo' => [],
        ];
        $results = $this->makeResults([
            'modules' => ['Vendor_Module' => $moduleData],
            'summary' => [
                'total' => 1,
                'compatible' => 0,
                'incompatible' => 1,
                'hyvaAware' => 0,
                'criticalIssues' => 1,
                'warningIssues' => 0,
            ],
            'hasIncompatibilities' => true,
        ]);
        $this->compatibilityChecker->method('check')->willReturn($results);
        $this->compatibilityChecker->method('formatResultsForDisplay')
            ->willReturn([['Vendor_Module', 'Incompatible', '1 critical']]);
        $this->compatibilityChecker->expects($this->once())
            ->method('getDetailedIssues')
            ->with($moduleData)
            ->willReturn([
                [
                    'file' => 'view/frontend/web/js/component.js',
                    'issues' => [
                        ['severity' => 'critical', 'line' => 10, 'description' => 'Uses RequireJS'],
                    ],
                ],
            ]);

        $tester = new CommandTester($this->command);
        $exitCode = $tester->execute(['--detailed' => true]);

        $this->assertSame(Cli::RETURN_FAILURE, $exitCode);
        $display = $tester->getDisplay();
        $this->assertStringContainsString('Detailed Issues', $display);
        $this->assertStringContainsString('component.js', $display);
        $this->assertStringContainsString('Uses RequireJS', $display);
    }

    public function testIncludeVendorOptionIsPassedToChecker(): void
    {
        $this->compatibilityChecker->expects($this->once())
            ->method('check')
            ->with($this->anything(), false, false, false)
            ->willReturn($this->makeResults());
        $this->compatibilityChecker->method('formatResultsForDisplay')->willReturn([]);

        $tester = new CommandTester($this->command);
        $tester->execute(['--include-vendor' => true]);
    }

    public function testCommandNameAndAliases(): void
    {
        $this->assertSame('mageforge:hyva:compatibility:check', $this->command->getName());
        $this->assertSame(['hyva:check'], $this->command->getAliases());
    }
}
