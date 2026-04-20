#!/usr/bin/env php
<?php declare(strict_types=1);
require_once __DIR__ . '/../vendor/autoload.php';

use AwesomeList\Discovery\CandidateFilter;
use AwesomeList\Discovery\CandidateIssueRenderer;
use AwesomeList\Discovery\CandidateLog;
use AwesomeList\Discovery\CategoryGuesser;
use AwesomeList\Discovery\DiscoveryScanner;
use AwesomeList\Discovery\ExistingUrlsIndex;
use AwesomeList\Discovery\GithubSearchClient;
use AwesomeList\Discovery\IssueUpserter;
use GuzzleHttp\Client;

$token = getenv('GITHUB_TOKEN') ?: null;
if ($token === null) {
    fwrite(STDERR, "GITHUB_TOKEN required\n");
    exit(1);
}
$repo = getenv('GITHUB_REPOSITORY') ?: 'run-as-root/awesome-magento2';
[$owner, $name] = explode('/', $repo, 2);

$http = new Client([
    'base_uri' => 'https://api.github.com/',
    'timeout'  => 30,
    'headers'  => [
        'Authorization' => "Bearer $token",
        'Accept'        => 'application/vnd.github+json',
        'User-Agent'    => 'awesome-magento2-discovery',
    ],
]);
$now = new DateTimeImmutable();

$index = ExistingUrlsIndex::build(__DIR__ . '/../data');
$logPath = __DIR__ . '/../state/candidates.log.json';
$log = CandidateLog::loadOrEmpty($logPath);

$scanner = new DiscoveryScanner(
    new GithubSearchClient($http),
    new CandidateFilter($now),
    new CategoryGuesser(),
);
$candidates = $scanner->scan($index, $log);

// Self-exclude: never recommend the repo running the scan.
$selfUrl = "https://github.com/$owner/$name";
$candidates = array_values(array_filter(
    $candidates,
    fn(array $c): bool => stripos($c['repo']->htmlUrl, $selfUrl) !== 0,
));

foreach ($candidates as $c) {
    $log = $log->markPending($c['repo']->htmlUrl, $c['suggested_yaml'], $c['repo']);
}
$log->save($logPath);

$body = (new CandidateIssueRenderer())->render($candidates, $log, $now);
$title = 'Magento 2 Discovery Candidates';
(new IssueUpserter($http, $owner, $name, $token))->upsert($title, $body);

echo "Discovered " . count($candidates) . " new candidates; log updated.\n";
