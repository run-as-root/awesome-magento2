#!/usr/bin/php
<?php declare(strict_types=1);

use AwesomeList\Feed\EventFeedGenerator;
use AwesomeList\MarkdownGenerator;
use AwesomeList\YamlEntryLoader;

require_once __DIR__ . '/vendor/autoload.php';

$markdownGenerator = new MarkdownGenerator();
$contents = $markdownGenerator->generate(__DIR__.'/content');
file_put_contents(__DIR__ . '/README.md', $contents);

$feed = new EventFeedGenerator(new YamlEntryLoader(), new DateTimeImmutable());
$artifacts = $feed->generate(__DIR__ . '/data/events');
file_put_contents(__DIR__ . '/events.ical', $artifacts['ical']);
file_put_contents(__DIR__ . '/events.json', $artifacts['json']);