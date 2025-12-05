<?php

declare(strict_types=1);

namespace OpenForgeProject\MageForge\Service;

use Magento\Framework\Filesystem\Driver\File;
use Symfony\Component\Console\Style\SymfonyStyle;
use Magento\Framework\Shell;
class DependencyChecker
{
    private const PACKAGE_JSON = 'package.json';
    private const PACKAGE_JSON_SAMPLE = 'package.json.sample';
    private const GRUNTFILE = 'Gruntfile.js';
    private const GRUNTFILE_SAMPLE = 'Gruntfile.js.sample';
    private const NODE_MODULES = 'node_modules';

    public function __construct(
        private readonly File $fileDriver,
        private readonly Shell $shell
    ) {
    }

    public function checkDependencies(SymfonyStyle $io, bool $isVerbose): bool
    {
        if (!$this->checkPackageJson($io, $isVerbose) || !$this->checkNodeModules($io, $isVerbose)) {
            return false;
        }
        if (!$this->checkFile($io, self::GRUNTFILE, self::GRUNTFILE_SAMPLE, $isVerbose)) {
            return false;
        }
        return true;
    }

    private function checkPackageJson(SymfonyStyle $io, bool $isVerbose): bool
    {
        if (!$this->fileDriver->isFile(self::PACKAGE_JSON)) {
            if ($isVerbose) {
                $io->warning("The 'package.json' file does not exist in the Magento root path.");
            }
            if (!$this->fileDriver->isFile(self::PACKAGE_JSON_SAMPLE)) {
                if ($isVerbose) {
                    $io->warning("The 'package.json.sample' file does not exist in the Magento root path.");
                }
                $io->error("Skipping this theme build.");
                return false;
            } else {
                if ($isVerbose) {
                    $io->success("The 'package.json.sample' file found.");
                }
                if ($io->confirm("Copy 'package.json.sample' to 'package.json'?", false)) {
                    $this->fileDriver->copy(self::PACKAGE_JSON_SAMPLE, self::PACKAGE_JSON);
                    if ($isVerbose) {
                        $io->success("'package.json.sample' has been copied to 'package.json'.");
                    }
                }
            }
        } elseif ($isVerbose) {
            $io->success("The 'package.json' file found.");
        }
        return true;
    }

    private function checkNodeModules(SymfonyStyle $io, bool $isVerbose): bool
    {
        if (!$this->fileDriver->isDirectory(self::NODE_MODULES)) {
            if ($isVerbose) {
                $io->warning("The 'node_modules' folder does not exist in the Magento root path.");
            }
            if ($io->confirm("Run 'npm install' to install the dependencies?", false)) {
                if ($isVerbose) {
                    $io->section("Running 'npm install'... Please wait.");
                }
                try {
                    $shellOutput = $this->shell->execute('npm install --quiet');
                    if ($isVerbose) {
                        $io->writeln($shellOutput);
                        $io->success("'npm install' has been successfully executed.");
                    }
                } catch (\Exception $e) {
                    $io->error($e->getMessage());
                    return false;
                }
            } else {
                $io->error("Skipping this theme build.");
                return false;
            }
        } elseif ($isVerbose) {
            $io->success("The 'node_modules' folder found.");
        }
        return true;
    }

    private function checkFile(SymfonyStyle $io, string $file, string $sampleFile, bool $isVerbose): bool
    {
        if (!$this->fileDriver->isFile($file)) {
            if ($isVerbose) {
                $io->warning("The '$file' file does not exist in the Magento root path.");
            }
            if (!$this->fileDriver->isFile($sampleFile)) {
                if ($isVerbose) {
                    $io->warning("The '$sampleFile' file does not exist in the Magento root path.");
                }
                $io->error("Skipping this theme build.");
                return false;
            } else {
                if ($isVerbose) {
                    $io->success("The '$sampleFile' file found.");
                }
                if ($io->confirm("Copy '$sampleFile' to '$file'?", false)) {
                    $this->fileDriver->copy($sampleFile, $file);
                    if ($isVerbose) {
                        $io->success("'$sampleFile' has been copied to '$file'.");
                    }
                }
            }
        } elseif ($isVerbose) {
            $io->success("The '$file' file found.");
        }
        return true;
    }
}
