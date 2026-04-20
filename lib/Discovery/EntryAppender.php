<?php declare(strict_types=1);
namespace AwesomeList\Discovery;

final class EntryAppender
{
    /** @param array{name: string, url: string, description: string, type: string, added: string} $entry */
    public function append(string $filePath, array $entry): void
    {
        $yaml  = "- name: {$entry['name']}\n";
        $yaml .= "  url: {$entry['url']}\n";
        $yaml .= "  description: {$this->escape($entry['description'])}\n";
        $yaml .= "  type: {$entry['type']}\n";
        $yaml .= "  added: \"{$entry['added']}\"\n";

        if (!is_file($filePath)) {
            $parent = dirname($filePath);
            if (!is_dir($parent)) {
                mkdir($parent, 0755, true);
            }
            file_put_contents($filePath, $yaml);
            return;
        }
        $current = file_get_contents($filePath);
        if (!str_ends_with($current, "\n")) {
            $current .= "\n";
        }
        file_put_contents($filePath, $current . $yaml);
    }

    private function escape(string $s): string
    {
        // Descriptions with YAML metacharacters get double-quoted.
        if (preg_match('/[:#&*!|>\'"%@`]/', $s)) {
            return '"' . addcslashes($s, '"\\') . '"';
        }
        return $s;
    }
}
