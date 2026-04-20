#!/usr/bin/env php
<?php declare(strict_types=1);
require_once __DIR__ . '/../vendor/autoload.php';

use AwesomeList\Discovery\EntryRemover;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;

const GRAVEYARD_DAYS = 90;

$dataDir   = __DIR__ . '/../data';
$statePath = __DIR__ . '/../state/enrichment.json';
$summaryPath = getenv('SUMMARY_PATH') ?: __DIR__ . '/../state/last-prune-summary.md';

if (!is_file($statePath)) {
    fwrite(STDERR, "state/enrichment.json missing — run `composer enrich` first.\n");
    exit(0);
}

$state = json_decode((string) file_get_contents($statePath), true, flags: JSON_THROW_ON_ERROR);
$now   = new DateTimeImmutable();

// Collect every URL where link_status has been broken for > GRAVEYARD_DAYS.
$candidates = [];
foreach ($state as $url => $record) {
    $status = $record['signals']['link_status'] ?? null;
    if ($status !== 'broken') {
        continue;
    }
    $since = $record['liveness']['link_status_since'] ?? null;
    if (!is_string($since)) {
        continue;
    }
    $sinceDt = new DateTimeImmutable($since);
    $days = $now->diff($sinceDt)->days;
    if ($days > GRAVEYARD_DAYS) {
        $candidates[] = ['url' => $url, 'days_broken' => (int) $days];
    }
}

if ($candidates === []) {
    file_put_contents($summaryPath, "No entries have been broken for more than " . GRAVEYARD_DAYS . " days.\n");
    echo "Nothing to prune.\n";
    exit(0);
}

// For each broken URL, find which YAML file it lives in and remove it.
$removed = [];
$remover = new EntryRemover();

$yamlFiles = [];
$it = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($dataDir));
foreach ($it as $f) {
    if ($f->isFile() && $f->getExtension() === 'yml') {
        $yamlFiles[] = $f->getPathname();
    }
}
sort($yamlFiles);

foreach ($candidates as $candidate) {
    foreach ($yamlFiles as $path) {
        if ($remover->remove($path, $candidate['url'])) {
            $removed[] = [
                'url'         => $candidate['url'],
                'days_broken' => $candidate['days_broken'],
                'file'        => substr($path, strlen(dirname($dataDir)) + 1),
            ];
            break;
        }
    }
}

file_put_contents($summaryPath, renderSummary($removed));

echo "Pruned " . count($removed) . " dead entries.\n";

/**
 * @param array<int, array{url: string, days_broken: int, file: string}> $removed
 */
function renderSummary(array $removed): string
{
    if ($removed === []) {
        return "No entries removed.\n";
    }
    $byFile = [];
    foreach ($removed as $r) {
        $byFile[$r['file']][] = $r;
    }
    ksort($byFile);

    $lines = [];
    $lines[] = sprintf('Removes %d dead %s that have returned non-2xx for more than %d days.', count($removed), count($removed) === 1 ? 'entry' : 'entries', GRAVEYARD_DAYS);
    $lines[] = '';
    foreach ($byFile as $file => $entries) {
        $lines[] = sprintf('### `%s` (%d)', $file, count($entries));
        $lines[] = '';
        foreach ($entries as $e) {
            $lines[] = sprintf('- %s — broken for %d days', $e['url'], $e['days_broken']);
        }
        $lines[] = '';
    }
    $lines[] = '---';
    $lines[] = '_Merging this PR removes the entries outright. The enrichment sidecar still remembers them, so if any URL comes back online the next discovery run can surface it again as a fresh candidate._';
    return implode("\n", $lines) . "\n";
}
