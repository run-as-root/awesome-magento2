#!/usr/bin/env php
<?php declare(strict_types=1);
require_once __DIR__ . '/../vendor/autoload.php';

use AwesomeList\Enrichment\AdapterFactory;
use AwesomeList\Enrichment\Enricher;
use AwesomeList\Enrichment\GithubRepoAdapter;
use AwesomeList\Enrichment\VitalityRanker;
use AwesomeList\YamlEntryLoader;
use GuzzleHttp\Client;

$token = getenv('GITHUB_TOKEN') ?: null;
$headers = ['User-Agent' => 'awesome-magento2-enricher'];
if ($token !== null) {
    $headers['Authorization'] = "Bearer $token";
}

$http = new Client([
    'base_uri' => 'https://api.github.com/',
    'timeout'  => 15,
    'headers'  => $headers,
]);

$enricher = new Enricher(
    new YamlEntryLoader(),
    new AdapterFactory([new GithubRepoAdapter($http, new DateTimeImmutable())]),
    new VitalityRanker(),
);

$state = $enricher->enrichDirectory(__DIR__ . '/../data');
$path  = __DIR__ . '/../state/enrichment.json';
file_put_contents($path, json_encode($state, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . "\n");

$count = count($state);
echo "Enriched $count entries → $path\n";
