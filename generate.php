#!/usr/bin/php
<?php declare(strict_types=1);

use AwesomeList\MarkdownGenerator;
use AwesomeList\Parser\GenericCsvList;

require_once __DIR__ . '/vendor/autoload.php';

$markdownGenerator = new MarkdownGenerator();
$contents = $markdownGenerator->generate(__DIR__.'/content');

file_put_contents(__DIR__ . '/README.md.new', $contents);