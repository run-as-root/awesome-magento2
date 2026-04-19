<?php declare(strict_types=1);
namespace AwesomeList;

use Symfony\Component\Yaml\Yaml;
use RuntimeException;

final class YamlEntryLoader
{
    /** @return Entry[] */
    public function load(string $path): array
    {
        if (!is_file($path)) {
            throw new RuntimeException("YAML file not found: $path");
        }
        $rows = Yaml::parseFile($path) ?? [];
        if (!is_array($rows)) {
            throw new RuntimeException("Expected a list at the root of $path");
        }

        return array_map(
            fn(array $row): Entry => new Entry(
                name:         (string) $row['name'],
                url:          $row['url'] ?? null,
                description:  $row['description'] ?? null,
                type:         EntryType::from($row['type']),
                added:        (string) $row['added'],
                pinned:       (bool) ($row['pinned'] ?? false),
                pinReason:    $row['pin_reason'] ?? null,
                typeSpecific: array_diff_key($row, array_flip([
                    'name', 'url', 'description', 'type', 'added', 'pinned', 'pin_reason',
                ])),
            ),
            $rows,
        );
    }
}
