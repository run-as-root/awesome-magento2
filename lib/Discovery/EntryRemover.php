<?php declare(strict_types=1);
namespace AwesomeList\Discovery;

final class EntryRemover
{
    /**
     * Remove the YAML entry whose `url:` matches $url from $filePath. Preserves the
     * surrounding entries' formatting byte-for-byte — we don't round-trip through
     * symfony/yaml so existing indentation, comments, and ordering stay intact.
     *
     * Returns true if an entry was removed, false if no match.
     */
    public function remove(string $filePath, string $url): bool
    {
        if (!is_file($filePath)) {
            return false;
        }
        $content = file_get_contents($filePath);
        if ($content === false) {
            return false;
        }
        $lines = explode("\n", $content);

        $targetIdx = null;
        foreach ($lines as $i => $line) {
            if (preg_match('/^\s+url:\s*' . preg_quote($url, '/') . '\s*$/', $line)) {
                $targetIdx = $i;
                break;
            }
        }
        if ($targetIdx === null) {
            return false;
        }

        // Walk back to the `- name:` line that opens this block.
        $start = $targetIdx;
        while ($start > 0 && !str_starts_with($lines[$start], '- ')) {
            $start--;
        }
        // Walk forward to the next block start or EOF.
        $end = $start + 1;
        while ($end < count($lines) && !str_starts_with($lines[$end], '- ')) {
            $end++;
        }

        array_splice($lines, $start, $end - $start);
        file_put_contents($filePath, implode("\n", $lines));
        return true;
    }
}
