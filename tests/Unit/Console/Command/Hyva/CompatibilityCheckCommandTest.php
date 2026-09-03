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
    /**
     * @var CompatibilityChecker&MockObject
     */
    private $compatibilityChecker;
    /**
     * @var CompatibilityCheckCommand
     */
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
        $display = $tester->getDisplay();
        $this->assertStringContainsString('Hyvä Theme Compatibility Check', $display);
        $this->assertStringContainsString('Compatibility Results', $display);
        $this->assertStringContainsString('All scanned modules are compatible with Hyv', $display);
        // The empty-table early return means the results table itself (with its headers) must
        // never be rendered.
        $this->assertStringNotContainsString('Status', $display);
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
        $this->assertStringContainsString('Module', $display);
        $this->assertStringContainsString('Status', $display);
        $this->assertStringContainsString('Issues', $display);
        $this->assertStringContainsString('Vendor_Module', $display);
        $this->assertStringContainsString('critical compatibility issue', $display);
        $this->assertStringNotContainsString('warning(s)', $display);
        $this->assertStringNotContainsString('Hyvä compatible!', $display);
        $this->assertStringContainsString('Recommendations', $display);
        $this->assertStringContainsString('Check if Hyvä compatibility packages exist', $display);
        $this->assertStringContainsString('hyva.io/compatibility', $display);
        $this->assertStringContainsString('refactoring RequireJS/Knockout code to Alpine.js', $display);
        $this->assertStringContainsString('Contact module vendors for Hyvä-compatible versions', $display);
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
        $display = $tester->getDisplay();
        $this->assertStringContainsString('warning(s)', $display);
        $this->assertStringNotContainsString('critical compatibility issue', $display);
        $this->assertStringNotContainsString('Hyvä compatible!', $display);
    }

    public function testDisplaySummaryRendersEachDistinctFigure(): void
    {
        $results = $this->makeResults([
            'summary' => [
                'total' => 11,
                'compatible' => 7,
                'incompatible' => 4,
                'hyvaAware' => 3,
                'criticalIssues' => 2,
                'warningIssues' => 5,
            ],
            'hasIncompatibilities' => true,
        ]);
        $this->compatibilityChecker->method('check')->willReturn($results);
        $this->compatibilityChecker->method('formatResultsForDisplay')->willReturn([]);

        $tester = new CommandTester($this->command);
        $tester->execute([]);

        $display = $tester->getDisplay();
        $this->assertStringContainsString('Summary', $display);
        $this->assertStringContainsString('Total Modules Scanned', $display);
        $this->assertStringContainsString('11', $display);
        $this->assertStringContainsString('Compatible', $display);
        $this->assertStringContainsString('7', $display);
        $this->assertStringContainsString('Incompatible', $display);
        $this->assertStringContainsString('4', $display);
        $this->assertStringContainsString('Hyvä-Aware Modules', $display);
        $this->assertStringContainsString('3', $display);
        $this->assertStringContainsString('Critical Issues', $display);
        $this->assertStringContainsString('2', $display);
        $this->assertStringContainsString('Warnings', $display);
        $this->assertStringContainsString('5', $display);
    }

    public function testExactlyZeroCriticalIssuesWithWarningsShowsWarningMessageNotCriticalMessage(): void
    {
        $results = $this->makeResults([
            'summary' => [
                'total' => 2,
                'compatible' => 1,
                'incompatible' => 1,
                'hyvaAware' => 0,
                'criticalIssues' => 0,
                'warningIssues' => 3,
            ],
            'hasIncompatibilities' => true,
        ]);
        $this->compatibilityChecker->method('check')->willReturn($results);
        $this->compatibilityChecker->method('formatResultsForDisplay')->willReturn([]);

        $tester = new CommandTester($this->command);
        $tester->execute([]);

        $display = $tester->getDisplay();
        $this->assertStringContainsString('Found', $display);
        $this->assertStringContainsString('3 warning(s)', $display);
        $this->assertStringContainsString('in 2 scanned modules', $display);
        $this->assertStringContainsString('Review these modules for potential compatibility issues.', $display);
        $this->assertStringNotContainsString('critical compatibility issue', $display);
    }

    public function testOneCriticalIssueShowsCriticalMessageWithExactCounts(): void
    {
        $results = $this->makeResults([
            'summary' => [
                'total' => 6,
                'compatible' => 5,
                'incompatible' => 1,
                'hyvaAware' => 0,
                'criticalIssues' => 1,
                'warningIssues' => 0,
            ],
            'hasIncompatibilities' => true,
        ]);
        $this->compatibilityChecker->method('check')->willReturn($results);
        $this->compatibilityChecker->method('formatResultsForDisplay')->willReturn([]);

        $tester = new CommandTester($this->command);
        $tester->execute([]);

        $display = $tester->getDisplay();
        $this->assertStringContainsString('1 critical compatibility issue(s)', $display);
        $this->assertStringContainsString('in 6 scanned modules', $display);
        $this->assertStringContainsString('These modules require modifications to work with Hyv', $display);
    }

    public function testShowAllOptionIsForwardedToFormatResultsForDisplay(): void
    {
        $this->compatibilityChecker->method('check')->willReturn($this->makeResults());
        $this->compatibilityChecker->expects($this->once())
            ->method('formatResultsForDisplay')
            ->with($this->anything(), true)
            ->willReturn([]);

        $tester = new CommandTester($this->command);
        $tester->execute(['--show-all' => true]);
    }

    public function testWithoutShowAllOptionFormatResultsForDisplayReceivesFalse(): void
    {
        $this->compatibilityChecker->method('check')->willReturn($this->makeResults());
        $this->compatibilityChecker->expects($this->once())
            ->method('formatResultsForDisplay')
            ->with($this->anything(), false)
            ->willReturn([]);

        $tester = new CommandTester($this->command);
        $tester->execute([]);
    }

    public function testDetailedFlagWithoutIncompatibilitiesSkipsDetailedIssues(): void
    {
        $this->compatibilityChecker->method('check')->willReturn($this->makeResults(['hasIncompatibilities' => false]));
        $this->compatibilityChecker->method('formatResultsForDisplay')->willReturn([]);
        $this->compatibilityChecker->expects($this->never())->method('getDetailedIssues');

        $tester = new CommandTester($this->command);
        $tester->execute(['--detailed' => true]);

        $this->assertStringNotContainsString('Detailed Issues', $tester->getDisplay());
    }

    public function testIncompatibilitiesWithoutDetailedFlagSkipsDetailedIssues(): void
    {
        $results = $this->makeResults([
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
        $this->compatibilityChecker->method('formatResultsForDisplay')->willReturn([]);
        $this->compatibilityChecker->expects($this->never())->method('getDetailedIssues');

        $tester = new CommandTester($this->command);
        $tester->execute([]);

        $this->assertStringNotContainsString('Detailed Issues', $tester->getDisplay());
    }

    public function testDetailedOptionDisplaysFileLevelIssues(): void
    {
        $moduleData = [
            'compatible' => false,
            'hasWarnings' => false,
            'scanResult' => ['files' => [], 'criticalIssues' => 1, 'totalIssues' => 1],
            'moduleInfo' => [],
        ];
        // Fully compatible with no warnings: must be skipped entirely (never queried for
        // detailed issues, never mentioned in the detailed output).
        $cleanModuleData = [
            'compatible' => true,
            'hasWarnings' => false,
            'scanResult' => ['files' => [], 'criticalIssues' => 0, 'totalIssues' => 0],
            'moduleInfo' => [],
        ];
        $results = $this->makeResults([
            'modules' => ['Vendor_Module' => $moduleData, 'Vendor_Clean' => $cleanModuleData],
            'summary' => [
                'total' => 2,
                'compatible' => 1,
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
                        ['severity' => 'warning', 'line' => 20, 'description' => 'Uses jQuery'],
                    ],
                ],
            ]);

        $tester = new CommandTester($this->command);
        $exitCode = $tester->execute(['--detailed' => true]);

        $this->assertSame(Cli::RETURN_FAILURE, $exitCode);
        $display = $tester->getDisplay();
        $this->assertStringContainsString('Detailed Issues', $display);
        $this->assertStringContainsString('Vendor_Module', $display);
        $this->assertStringContainsString('component.js', $display);
        $this->assertStringContainsString('✗ Line 10: Uses RequireJS', $display);
        $this->assertStringContainsString('⚠ Line 20: Uses jQuery', $display);
        $this->assertStringNotContainsString('Vendor_Clean', $display);
    }

    public function testDetailedIssuesIncludesCompatibleModulesThatHaveWarnings(): void
    {
        $warnedModuleData = [
            'compatible' => true,
            'hasWarnings' => true,
            'scanResult' => ['files' => [], 'criticalIssues' => 0, 'totalIssues' => 1],
            'moduleInfo' => [],
        ];
        $results = $this->makeResults([
            'modules' => ['Vendor_Warned' => $warnedModuleData],
            'summary' => [
                'total' => 1,
                'compatible' => 1,
                'incompatible' => 0,
                'hyvaAware' => 0,
                'criticalIssues' => 0,
                'warningIssues' => 1,
            ],
            'hasIncompatibilities' => true,
        ]);
        $this->compatibilityChecker->method('check')->willReturn($results);
        $this->compatibilityChecker->method('formatResultsForDisplay')->willReturn([]);
        $this->compatibilityChecker->expects($this->once())
            ->method('getDetailedIssues')
            ->with($warnedModuleData)
            ->willReturn([]);

        $tester = new CommandTester($this->command);
        $tester->execute(['--detailed' => true]);

        $this->assertStringContainsString('Vendor_Warned', $tester->getDisplay());
    }

    public function testIncludeCoreOptionIsPassedToChecker(): void
    {
        $this->compatibilityChecker->expects($this->once())
            ->method('check')
            ->with($this->anything(), false, false, false)
            ->willReturn($this->makeResults());
        $this->compatibilityChecker->method('formatResultsForDisplay')->willReturn([]);

        $tester = new CommandTester($this->command);
        $tester->execute(['--include-core' => true]);
    }

    public function testExcludeVendorOptionIsPassedToChecker(): void
    {
        $this->compatibilityChecker->expects($this->once())
            ->method('check')
            ->with($this->anything(), false, true, true)
            ->willReturn($this->makeResults());
        $this->compatibilityChecker->method('formatResultsForDisplay')->willReturn([]);

        $tester = new CommandTester($this->command);
        $tester->execute(['--exclude-vendor' => true]);
    }

    public function testConflictingThirdPartyOnlyAndIncludeCoreOptionsReturnError(): void
    {
        $this->compatibilityChecker->expects($this->never())->method('check');

        $tester = new CommandTester($this->command);
        $exitCode = $tester->execute([
            '--third-party-only' => true,
            '--include-core' => true,
        ]);

        $this->assertSame(Cli::RETURN_FAILURE, $exitCode);
        $this->assertStringContainsString(
            'cannot be used together',
            $tester->getDisplay(),
        );
    }

    public function testCommandNameAndAliases(): void
    {
        $this->assertSame('mageforge:hyva:compatibility:check', $this->command->getName());
        $this->assertSame(['hyva:check'], $this->command->getAliases());
    }
}
