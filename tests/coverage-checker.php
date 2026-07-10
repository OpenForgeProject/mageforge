<?php

/**
 * Fails (exit 1) when line coverage in a Clover report drops below the given minimum.
 *
 * Usage: php tests/coverage-checker.php <clover.xml> <min-line-coverage-percent>
 *
 * Raise the threshold as coverage grows — it is a ratchet, not a target.
 */

declare(strict_types=1);

// Standalone CLI script (not Magento application code): process exit codes and direct
// stdout are the intended interface, so the Magento2 security sniffs don't apply here.
// phpcs:disable Magento2.Security.LanguageConstruct.ExitUsage, Magento2.Security.LanguageConstruct.DirectOutput

if ($argc !== 3) {
    fwrite(STDERR, "Usage: php tests/coverage-checker.php <clover.xml> <min-line-coverage-percent>\n");
    exit(2);
}

[, $cloverFile, $minCoverage] = $argv;

if (!is_file($cloverFile)) {
    fwrite(STDERR, sprintf("Clover file not found: %s\n", $cloverFile));
    exit(2);
}

$xml = simplexml_load_file($cloverFile);
if ($xml === false || !isset($xml->project->metrics)) {
    fwrite(STDERR, sprintf("Could not parse Clover metrics from %s\n", $cloverFile));
    exit(2);
}

$metrics = $xml->project->metrics;
$statements = (int) $metrics['statements'];
$covered = (int) $metrics['coveredstatements'];
$coverage = $statements > 0 ? $covered / $statements * 100 : 0.0;

printf("Line coverage: %.2f%% (%d/%d statements, minimum: %s%%)\n", $coverage, $covered, $statements, $minCoverage);

if ($coverage < (float) $minCoverage) {
    fwrite(STDERR, "FAILED: line coverage dropped below the minimum threshold.\n");
    exit(1);
}

echo "OK\n";
