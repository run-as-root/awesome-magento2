#!/usr/bin/php
<?php declare(strict_types=1);

use AwesomeList\Feed\EventFeedGenerator;
use AwesomeList\MarkdownGenerator;
use AwesomeList\Rendering\Stats;
use AwesomeList\Rendering\Toc;
use AwesomeList\SidecarState;
use AwesomeList\YamlEntryLoader;

require_once __DIR__ . '/vendor/autoload.php';

$markdownGenerator = new MarkdownGenerator();
$contents = $markdownGenerator->generate(__DIR__.'/content');

// Inject the stats block + auto-generated TOC after the tag-expansion pass.
$state = SidecarState::loadOrEmpty(__DIR__ . '/state/enrichment.json');
$stats = new Stats(new YamlEntryLoader());
$statsBlock = $stats->render($stats->collect(__DIR__ . '/data', $state));
$contents = str_replace('<!-- STATS -->', $statsBlock, $contents);

$tocBlock = (new Toc())->render($contents);
$contents = str_replace('<!-- TOC -->', $tocBlock, $contents);

file_put_contents(__DIR__ . '/README.md', $contents);

$feed = new EventFeedGenerator(new YamlEntryLoader(), new DateTimeImmutable());
$artifacts = $feed->generate(__DIR__ . '/data/events');
file_put_contents(__DIR__ . '/events.ical', $artifacts['ical']);
file_put_contents(__DIR__ . '/events.json', $artifacts['json']);