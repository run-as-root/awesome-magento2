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

$accepted = [];
foreach ($parsed as $row) {
    if (!$row['checked']) {
        if ($log->statusOf($row['url']) === 'pending') {
            $log = $log->markRejected($row['url']);
        }
        continue;
    }
    if ($log->statusOf($row['url']) === 'accepted') {
        continue;
    }
    $path = parse_url($row['url'], PHP_URL_PATH) ?? '';
    $description = $row['url'];
    try {
        $resp = $http->get('repos' . $path);
        $meta = json_decode((string) $resp->getBody(), true, flags: JSON_THROW_ON_ERROR);
        $description = (string) ($meta['description'] ?? $row['url']);
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
    $accepted[] = $entry;
}
$log->save($logPath);

echo "Processed " . count($accepted) . " accepted candidates.\n";
