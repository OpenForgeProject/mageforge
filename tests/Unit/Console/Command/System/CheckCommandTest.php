<?php

declare(strict_types=1);

namespace OpenForgeProject\MageForge\Test\Unit\Console\Command\System;

use Magento\Framework\App\ProductMetadataInterface;
use Magento\Framework\App\ResourceConnection;
use Magento\Framework\Console\Cli;
use Magento\Framework\DB\Adapter\AdapterInterface;
use Magento\Framework\Escaper;
use Magento\Framework\HTTP\ClientFactory;
use Magento\Framework\Shell;
use OpenForgeProject\MageForge\Console\Command\System\CheckCommand;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Console\Tester\CommandTester;

class CheckCommandTest extends TestCase
{
    private const NODE_LTS_URL = 'https://nodejs.org/dist/index.json';
    private const NODE_LTS_BODY = '[{"version": "v23.1.0", "lts": false}, {"version": "v22.13.0", "lts": "Jod"}]';

    /**
     * @var ProductMetadataInterface&MockObject
     */
    private $productMetadata;
    /**
     * @var ResourceConnection&MockObject
     */
    private $resourceConnection;
    /**
     * @var ClientFactory&MockObject
     */
    private $httpClientFactory;
    /**
     * @var Shell&MockObject
     */
    private $shell;

    protected function setUp(): void
    {
        $this->productMetadata = $this->createMock(ProductMetadataInterface::class);
        $this->productMetadata->method('getVersion')->willReturn('2.4.8');
        $this->resourceConnection = $this->createMock(ResourceConnection::class);
        $this->httpClientFactory = $this->createMock(ClientFactory::class);
        $this->shell = $this->createMock(Shell::class);
    }

    public function testCommandNameAndAlias(): void
    {
        $command = $this->createCommand();

        $this->assertSame('mageforge:system:check', $command->getName());
        $this->assertSame(['system:check'], $command->getAliases());
    }

    public function testDisplaysFullSystemReport(): void
    {
        $this->givenShellVersions([
            'node -v 2>/dev/null' => ' v20.11.0 ',
            'composer --version 2>/dev/null' => 'Composer version 2.7.1 2024-02-09 15:26:28',
            'npm --version 2>/dev/null' => '10.2.4',
            'git --version 2>/dev/null' => 'git version 2.44.0',
        ]);
        $this->givenDatabaseVersion('10.6.18-MariaDB');
        $this->givenHttpResponses([self::NODE_LTS_URL => [200, self::NODE_LTS_BODY]]);

        $tester = new CommandTester($this->createCommand());
        $exitCode = $tester->execute([]);

        $this->assertSame(Cli::RETURN_SUCCESS, $exitCode);
        $display = (string) preg_replace('/\s+/', ' ', $tester->getDisplay());
        $this->assertStringContainsString('System Components', $display);
        $this->assertStringContainsString('PHP ' . PHP_VERSION, $display);
        $this->assertStringContainsString('Memory limit:', $display);
        $this->assertStringContainsString('2.7.1', $display);
        $this->assertStringContainsString('20.11.0 (Latest LTS: 22.13.0)', $display);
        $this->assertStringNotContainsString('v20.11.0', $display, 'The v prefix must be stripped');
        $this->assertStringContainsString('NPM 10.2.4', $display);
        $this->assertStringContainsString('Git 2.44.0', $display);
        $this->assertStringContainsString('MySQL 10.6.18-MariaDB', $display);
        $this->assertStringContainsString('Magento 2.4.8', $display);
        $this->assertStringContainsString('PHP Extensions', $display);
        $this->assertStringContainsString('curl Enabled', $display);
        $this->assertStringContainsString('pdo_mysql', $display);
        $this->assertStringContainsString('Disk Space', $display);
        $this->assertStringContainsString('GB', $display);
        $this->assertStringContainsString('Xdebug', $display);
        $this->assertStringContainsString('Redis', $display);
        $this->assertStringContainsString('Search Engine', $display);
        $this->assertStringContainsString('OS ' . php_uname('s'), $display);
        $this->assertStringNotContainsString(
            'Failed to read MySQL version',
            $display,
            'Probe warnings must stay silent in non-verbose mode',
        );
    }

    public function testReportsOpenSearchWhenConnectionSucceeds(): void
    {
        $this->givenShellVersions(['node -v 2>/dev/null' => 'v22.13.0']);
        $this->givenDatabaseVersion('8.0.36');
        $this->givenHttpResponses([
            self::NODE_LTS_URL => [200, self::NODE_LTS_BODY],
            'http://localhost:9200' => [200, '{"version": {"distribution": "opensearch", "number": "2.12.0"}}'],
        ]);

        $tester = new CommandTester($this->createCommand());
        $exitCode = $tester->execute([]);

        $this->assertSame(Cli::RETURN_SUCCESS, $exitCode);
        $this->assertStringContainsString('OpenSearch 2.12.0', $tester->getDisplay());
    }

    public function testReportsElasticsearchWhenDistributionIsMissing(): void
    {
        $this->givenShellVersions(['node -v 2>/dev/null' => 'v22.13.0']);
        $this->givenDatabaseVersion('8.0.36');
        $this->givenHttpResponses([
            self::NODE_LTS_URL => [200, self::NODE_LTS_BODY],
            'http://127.0.0.1:9200' => [200, '{"version": {"number": "8.11.3"}}'],
        ]);

        $tester = new CommandTester($this->createCommand());
        $exitCode = $tester->execute([]);

        $this->assertSame(Cli::RETURN_SUCCESS, $exitCode);
        $this->assertStringContainsString('Elasticsearch 8.11.3', $tester->getDisplay());
    }

    public function testReportsSearchEngineNotAvailableWhenNothingResponds(): void
    {
        $this->givenShellVersions(['node -v 2>/dev/null' => 'v22.13.0']);
        $this->givenDatabaseVersion('8.0.36');
        $this->givenHttpResponses([self::NODE_LTS_URL => [200, self::NODE_LTS_BODY]]);

        $tester = new CommandTester($this->createCommand());
        $exitCode = $tester->execute([]);

        $this->assertSame(Cli::RETURN_SUCCESS, $exitCode);
        $this->assertStringContainsString('Not Available', $tester->getDisplay());
    }

    public function testFallsBackToMysqlClientWhenMagentoConnectionFails(): void
    {
        $this->givenShellVersions([
            'node -v 2>/dev/null' => 'v22.13.0',
            'mysql --version 2>/dev/null' => 'mysql  Ver 15.1 Distrib 10.11.6-MariaDB, for debian-linux-gnu',
        ]);
        $this->resourceConnection->method('getConnection')->willThrowException(new \RuntimeException('no db'));
        $this->givenHttpResponses([self::NODE_LTS_URL => [200, self::NODE_LTS_BODY]]);

        $tester = new CommandTester($this->createCommand());
        $exitCode = $tester->execute([]);

        $this->assertSame(Cli::RETURN_SUCCESS, $exitCode);
        $this->assertStringContainsString('MySQL 10.11.6', $tester->getDisplay());
    }

    public function testReportsUnknownDatabaseWhenAllProbesFail(): void
    {
        $this->givenShellVersions(['node -v 2>/dev/null' => 'v22.13.0']);
        $this->resourceConnection->method('getConnection')->willThrowException(new \RuntimeException('no db'));
        $this->givenHttpResponses([self::NODE_LTS_URL => [200, self::NODE_LTS_BODY]]);

        $tester = new CommandTester($this->createCommand());
        $exitCode = $tester->execute([]);

        $this->assertSame(Cli::RETURN_SUCCESS, $exitCode);
        $this->assertStringContainsString('MySQL Unknown', $tester->getDisplay());
    }

    public function testReportsMissingToolsAsNotInstalled(): void
    {
        // Only node responds; composer/npm/git/mysql probes all fail.
        $this->givenShellVersions(['node -v 2>/dev/null' => 'v22.13.0']);
        $this->givenDatabaseVersion('8.0.36');
        $this->givenHttpResponses([self::NODE_LTS_URL => [200, self::NODE_LTS_BODY]]);

        $tester = new CommandTester($this->createCommand());
        $exitCode = $tester->execute([]);

        $this->assertSame(Cli::RETURN_SUCCESS, $exitCode);
        $display = (string) preg_replace('/\s+/', ' ', $tester->getDisplay());
        $this->assertStringContainsString('Composer Not installed', $display);
        $this->assertStringContainsString('NPM Not installed', $display);
        $this->assertStringContainsString('Git Not installed', $display);
    }

    public function testMarksOutdatedNodeVersionAgainstLatestLts(): void
    {
        $this->givenShellVersions(['node -v 2>/dev/null' => 'v18.19.0']);
        $this->givenDatabaseVersion('8.0.36');
        $this->givenHttpResponses([self::NODE_LTS_URL => [200, self::NODE_LTS_BODY]]);

        $tester = new CommandTester($this->createCommand());
        $exitCode = $tester->execute([]);

        $this->assertSame(Cli::RETURN_SUCCESS, $exitCode);
        $this->assertStringContainsString('18.19.0 (Latest LTS: 22.13.0)', $tester->getDisplay());
    }

    public function testLtsFetchFailureFallsBackToUnknown(): void
    {
        $this->givenShellVersions(['node -v 2>/dev/null' => 'v22.13.0']);
        $this->givenDatabaseVersion('8.0.36');
        $this->givenHttpResponses([]);

        $tester = new CommandTester($this->createCommand());
        $exitCode = $tester->execute([]);

        $this->assertSame(Cli::RETURN_SUCCESS, $exitCode);
        $this->assertStringContainsString('(Latest LTS: Unknown)', $tester->getDisplay());
    }

    private function createCommand(): CheckCommand
    {
        return new CheckCommand(
            $this->productMetadata,
            new Escaper(),
            $this->resourceConnection,
            $this->httpClientFactory,
            $this->shell,
        );
    }

    /**
     * Configure the shell mock; probes for unlisted commands fail.
     *
     * @param array<string, string> $outputsByCommand
     */
    private function givenShellVersions(array $outputsByCommand): void
    {
        $this->shell
            ->method('execute')
            ->willReturnCallback(static function (string $command) use ($outputsByCommand): string {
                if (!isset($outputsByCommand[$command])) {
                    throw new \RuntimeException("command not found: {$command}");
                }
                return $outputsByCommand[$command];
            });
    }

    private function givenDatabaseVersion(string $version): void
    {
        $connection = $this->createMock(AdapterInterface::class);
        $connection->method('fetchOne')->with('SELECT VERSION()')->willReturn($version);
        $this->resourceConnection->method('getConnection')->willReturn($connection);
    }

    /**
     * @param array<string, array{int, string}> $responsesByUrl
     */
    private function givenHttpResponses(array $responsesByUrl): void
    {
        $this->httpClientFactory
            ->method('create')
            ->willReturnCallback(static fn(): FakeHttpClient => new FakeHttpClient($responsesByUrl));
    }

    public function testNon200LtsResponseFallsBackToUnknown(): void
    {
        $this->givenShellVersions(['node -v 2>/dev/null' => 'v22.13.0']);
        $this->givenDatabaseVersion('8.0.36');
        $this->givenHttpResponses([self::NODE_LTS_URL => [500, self::NODE_LTS_BODY]]);

        $tester = new CommandTester($this->createCommand());
        $tester->execute([]);

        $this->assertStringContainsString('(Latest LTS: Unknown)', $tester->getDisplay());
    }

    public function testOutdatedNodeVersionIsHighlightedInDecoratedOutput(): void
    {
        $this->givenShellVersions(['node -v 2>/dev/null' => 'v18.19.0']);
        $this->givenDatabaseVersion('8.0.36');
        $this->givenHttpResponses([self::NODE_LTS_URL => [200, self::NODE_LTS_BODY]]);

        $tester = new CommandTester($this->createCommand());
        $tester->execute([], ['decorated' => true]);

        $this->assertStringContainsString(
            "\033[33m18.19.0\033[39m",
            $tester->getDisplay(),
            'Outdated node versions must be highlighted in yellow',
        );
    }

    public function testCurrentNodeVersionIsNotHighlighted(): void
    {
        $this->givenShellVersions(['node -v 2>/dev/null' => 'v22.13.0']);
        $this->givenDatabaseVersion('8.0.36');
        $this->givenHttpResponses([self::NODE_LTS_URL => [200, self::NODE_LTS_BODY]]);

        $tester = new CommandTester($this->createCommand());
        $tester->execute([], ['decorated' => true]);

        $this->assertStringNotContainsString("\033[33m22.13.0", $tester->getDisplay());
    }
}
