#!/usr/bin/env php
<?php declare(strict_types=1);
require_once __DIR__ . '/../vendor/autoload.php';

use JsonSchema\Validator;
use Symfony\Component\Yaml\Yaml;

$schema = json_decode((string) file_get_contents(__DIR__ . '/../schemas/entry.schema.json'));
$files  = [];
$dataDir = __DIR__ . '/../data';
$iterator = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($dataDir));
foreach ($iterator as $file) {
    if ($file->isFile() && $file->getExtension() === 'yml') {
        $files[] = $file->getPathname();
    }
}

$exit = 0;
foreach ($files as $file) {
    $data      = Yaml::parseFile($file);
    $validator = new Validator();
    $validator->validate($data, $schema, \JsonSchema\Constraints\Constraint::CHECK_MODE_TYPE_CAST);
    if (!$validator->isValid()) {
        $exit = 1;
        echo "✗ $file\n";
        foreach ($validator->getErrors() as $err) {
            echo "  [{$err['property']}] {$err['message']}\n";
        }
    } else {
        echo "✓ $file\n";
    }
}
exit($exit);
