#!/usr/bin/env php
<?php declare(strict_types=1);
require_once __DIR__ . '/../vendor/autoload.php';

use AwesomeList\Enrichment\AdapterFactory;
use AwesomeList\Enrichment\ArchiveAdapter;
use AwesomeList\Enrichment\BlogAdapter;
use AwesomeList\Enrichment\Enricher;
use AwesomeList\Enrichment\GithubRepoAdapter;
use AwesomeList\Enrichment\LivenessAdapter;
use AwesomeList\Enrichment\PackagistAdapter;
use AwesomeList\Enrichment\VitalityRanker;
use AwesomeList\YamlEntryLoader;
use GuzzleHttp\Client;

$token = getenv('GITHUB_TOKEN') ?: null;
$headers = ['User-Agent' => 'awesome-magento2-enricher'];
if ($token !== null) {
    $headers['Authorization'] = "Bearer $token";
}

$githubHttp = new Client([
    'base_uri' => 'https://api.github.com/',
    'timeout'  => 15,
    'headers'  => $headers,
]);
$genericHttp = new Client([
    'timeout'         => 10,
    'allow_redirects' => true,
    'http_errors'     => false,
    'headers'         => ['User-Agent' => 'awesome-magento2-enricher'],
]);

$now = new DateTimeImmutable();

$enricher = new Enricher(
    new YamlEntryLoader(),
    new AdapterFactory([
        new GithubRepoAdapter($githubHttp, $now),
        new ArchiveAdapter($now),
        new PackagistAdapter($genericHttp, $now),
        new BlogAdapter($genericHttp, $now),
        new LivenessAdapter($genericHttp, $now, 'vendor_site'),
        new LivenessAdapter($genericHttp, $now, 'course'),
        new LivenessAdapter($genericHttp, $now, 'canonical'),
    ]),
    new VitalityRanker(),
);

$state = $enricher->enrichDirectory(__DIR__ . '/../data', __DIR__ . '/../state/enrichment.json');
$path  = __DIR__ . '/../state/enrichment.json';
file_put_contents($path, json_encode($state, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . "\n");

$count = count($state);
echo "Enriched $count entries → $path\n";
