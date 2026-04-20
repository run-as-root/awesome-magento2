<?php declare(strict_types=1);
namespace AwesomeList\Rendering;

final class Toc
{
    /**
     * Build a markdown bullet-list TOC from every `## ` heading in the document,
     * excluding the "Table of Contents" heading itself and anything inside fenced
     * code blocks. Emoji/punctuation in headings is stripped for the anchor.
     */
    public function render(string $markdown): string
    {
        $lines = [];
        $inFence = false;
        foreach (explode("\n", $markdown) as $line) {
            if (str_starts_with($line, '```')) {
                $inFence = !$inFence;
                continue;
            }
            if ($inFence) {
                continue;
            }
            if (!preg_match('/^## (?!Table of Contents\b)(.+?)$/u', $line, $m)) {
                continue;
            }
            $title = trim($m[1]);
            $lines[] = sprintf('- [%s](#%s)', $title, $this->slug($title));
        }
        return implode("\n", $lines);
    }

    private function slug(string $heading): string
    {
        // Mirror GitHub's anchor-generation rules: lowercase, drop non-word/dash,
        // collapse whitespace to '-'.
        $slug = strtolower($heading);
        $slug = preg_replace('/[^\p{L}\p{N}\s-]+/u', '', $slug) ?? $slug;
        $slug = preg_replace('/\s+/', '-', trim($slug)) ?? $slug;
        return $slug;
    }
}
