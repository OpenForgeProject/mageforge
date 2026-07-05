<?php

declare(strict_types=1);

namespace OpenForgeProject\MageForge\Test\Unit\Console\Command\System;

use GuzzleHttp\Client;
use GuzzleHttp\Handler\MockHandler;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Psr7\Response;
use Magento\Framework\Console\Cli;
use Magento\Framework\Filesystem\Driver\File;
use OpenForgeProject\MageForge\Console\Command\System\VersionCommand;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Console\Tester\CommandTester;

class VersionCommandTest extends TestCase
{
    private File&MockObject $fileDriver;

    protected function setUp(): void
    {
        $this->fileDriver = $this->createMock(File::class);
    }

    public function testCommandNameAndAlias(): void
    {
        $command = $this->createCommand([]);

        $this->assertSame('mageforge:system:version', $command->getName());
        $this->assertSame(['system:version'], $command->getAliases());
    }

    public function testDisplaysModuleAndLatestVersion(): void
    {
        $this->fileDriver->method('fileGetContents')->willReturn('{"version": "1.2.3"}');
        $command = $this->createCommand([new Response(200, [], '{"tag_name": "v9.9.9"}')]);

        $tester = new CommandTester($command);
        $exitCode = $tester->execute([]);

        $this->assertSame(Cli::RETURN_SUCCESS, $exitCode);
        $display = $tester->getDisplay();
        $this->assertStringContainsString('MageForge Version Information', $display);
        $this->assertStringContainsString('Module Version: 1.2.3', $display);
        $this->assertStringContainsString('Latest Version: v9.9.9', $display);
    }

    public function testDisplaysUnknownWhenComposerJsonHasNoVersion(): void
    {
        $this->fileDriver->method('fileGetContents')->willReturn('{"name": "openforgeproject/mageforge"}');
        $command = $this->createCommand([new Response(200, [], '{"tag_name": "v9.9.9"}')]);

        $tester = new CommandTester($command);
        $exitCode = $tester->execute([]);

        $this->assertSame(Cli::RETURN_SUCCESS, $exitCode);
        $this->assertStringContainsString('Module Version: Unknown', $tester->getDisplay());
    }

    public function testDisplaysUnknownWhenComposerJsonCannotBeRead(): void
    {
        $this->fileDriver->method('fileGetContents')->willThrowException(new \RuntimeException('read error'));
        $command = $this->createCommand([new Response(200, [], '{"tag_name": "v9.9.9"}')]);

        $tester = new CommandTester($command);
        $exitCode = $tester->execute([]);

        $this->assertSame(Cli::RETURN_SUCCESS, $exitCode);
        $this->assertStringContainsString('Module Version: Unknown', $tester->getDisplay());
    }

    public function testDisplaysUnknownLatestVersionWhenApiFails(): void
    {
        $this->fileDriver->method('fileGetContents')->willReturn('{"version": "1.2.3"}');
        $command = $this->createCommand([new Response(500, [], 'Server Error')]);

        $tester = new CommandTester($command);
        $exitCode = $tester->execute([]);

        $this->assertSame(Cli::RETURN_SUCCESS, $exitCode);
        $this->assertStringContainsString('Latest Version: Unknown', $tester->getDisplay());
    }

    public function testDisplaysUnknownLatestVersionForInvalidApiResponse(): void
    {
        $this->fileDriver->method('fileGetContents')->willReturn('{"version": "1.2.3"}');
        $command = $this->createCommand([new Response(200, [], 'not json')]);

        $tester = new CommandTester($command);
        $exitCode = $tester->execute([]);

        $this->assertSame(Cli::RETURN_SUCCESS, $exitCode);
        $this->assertStringContainsString('Latest Version: Unknown', $tester->getDisplay());
    }

    /**
     * Build the command with a Guzzle client that replays canned responses
     * instead of calling the GitHub API.
     *
     * @param array<int, Response> $responses
     */
    private function createCommand(array $responses): VersionCommand
    {
        $httpClient = new Client(['handler' => HandlerStack::create(new MockHandler($responses))]);

        return new VersionCommand($this->fileDriver, $httpClient);
    }
}
