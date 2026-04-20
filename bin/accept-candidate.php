#!/usr/bin/env php
<?php declare(strict_types=1);
require_once __DIR__ . '/../vendor/autoload.php';

use AwesomeList\Discovery\CandidateLog;
use AwesomeList\Discovery\CandidateParser;
use AwesomeList\Discovery\EntryAppender;
use GuzzleHttp\Client;

$token = getenv('GITHUB_TOKEN');
$repo  = getenv('GITHUB_REPOSITORY') ?: 'run-as-root/awesome-magento2';
[$owner, $name] = explode('/', $repo, 2);
$body = getenv('ISSUE_BODY') ?: '';
if ($body === '' || $token === false) {
    fwrite(STDERR, "ISSUE_BODY + GITHUB_TOKEN required\n");
    exit(1);
}

$http = new Client([
    'base_uri' => 'https://api.github.com/',
    'timeout'  => 30,
    'headers'  => [
        'Authorization' => "Bearer $token",
        'Accept'        => 'application/vnd.github+json',
        'User-Agent'    => 'awesome-magento2-accept-candidate',
    ],
]);

$parser = new CandidateParser();
$parsed = $parser->parse($body);

$logPath = __DIR__ . '/../state/candidates.log.json';
$log = CandidateLog::loadOrEmpty($logPath);
$appender = new EntryAppender();
$today = (new DateTimeImmutable())->format('Y-m-d');

// Accept-candidate is purely additive: only process freshly-checked boxes.
// Rejection is a separate concern (either left pending forever, or reset via
// the weekly discover workflow — never auto-rejected on the first partial tick).
$accepted = [];
foreach ($parsed as $row) {
    if (!$row['checked']) {
        continue;
    }
    if ($log->statusOf($row['url']) === 'accepted') {
        continue;
    }
    $path = parse_url($row['url'], PHP_URL_PATH) ?? '';
    $description = $row['url'];
    $stars = 0;
    try {
        $resp = $http->get('repos' . $path);
        $meta = json_decode((string) $resp->getBody(), true, flags: JSON_THROW_ON_ERROR);
        $description = (string) ($meta['description'] ?? $row['url']);
        $stars = (int) ($meta['stargazers_count'] ?? 0);
    } catch (\Throwable) {
        // fall back to url as description
    }
    $entry = [
        'name'        => basename($path),
        'url'         => $row['url'],
        'description' => $description,
        'type'        => 'github_repo',
        'added'       => $today,
    ];
    $target = __DIR__ . '/../data/' . $row['suggested_yaml'];
    if (!is_dir(dirname($target))) {
        mkdir(dirname($target), 0755, true);
    }
    $appender->append($target, $entry);
    $log = $log->markAccepted($row['url']);
    $accepted[] = $entry + ['suggested_yaml' => $row['suggested_yaml'], 'stars' => $stars];
}
$log->save($logPath);

// Emit a markdown summary for the PR body. Path is configurable so the
// workflow can point at /tmp or a runner scratch dir.
$summaryPath = getenv('SUMMARY_PATH') ?: __DIR__ . '/../state/last-accept-summary.md';
$issueNumber = getenv('ISSUE_NUMBER') ?: '';
$summary = renderSummary($accepted, $issueNumber);
file_put_contents($summaryPath, $summary);

echo "Processed " . count($accepted) . " accepted candidates.\n";

/**
 * @param array<int, array{name: string, url: string, description: string, type: string, added: string, suggested_yaml: string, stars: int}> $accepted
 */
function renderSummary(array $accepted, string $issueNumber): string
{
    if ($accepted === []) {
        return "No new candidates accepted.\n";
    }
    $byYaml = [];
    foreach ($accepted as $e) {
        $byYaml[$e['suggested_yaml']][] = $e;
    }
    ksort($byYaml);

    $lines = [];
    $lines[] = sprintf(
        'Accepts %d new %s%s.',
        count($accepted),
        count($accepted) === 1 ? 'candidate' : 'candidates',
        $issueNumber !== '' ? " from issue #$issueNumber" : '',
    );
    $lines[] = '';
    foreach ($byYaml as $yaml => $entries) {
        $lines[] = sprintf('### `data/%s` (%d)', $yaml, count($entries));
        $lines[] = '';
        foreach ($entries as $e) {
            $stars = $e['stars'] > 0 ? " ★{$e['stars']}" : '';
            $desc  = $e['description'] !== '' && $e['description'] !== $e['url']
                ? " — {$e['description']}"
                : '';
            $lines[] = sprintf('- [%s](%s)%s%s', $e['name'], $e['url'], $stars, $desc);
        }
        $lines[] = '';
    }
    $lines[] = '---';
    $lines[] = '_Merge this PR to close out the corresponding `accepted` entries in `state/candidates.log.json`. CI will rebuild `README.md` on merge._';
    return implode("\n", $lines) . "\n";
}
