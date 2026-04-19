<?php declare(strict_types=1);
namespace AwesomeList\Parser;

use AwesomeList\Entry;
use AwesomeList\Rendering\BadgeRenderer;
use AwesomeList\SidecarState;
use AwesomeList\YamlEntryLoader;

final class YamlEntryList implements ParserInterface
{
    private string $filename;
    private readonly YamlEntryLoader $loader;
    private readonly BadgeRenderer $badges;
    private readonly string $sidecarPath;

    public function __construct(
        ?YamlEntryLoader $loader = null,
        ?BadgeRenderer $badges = null,
        ?string $sidecarPath = null,
    ) {
        $this->loader      = $loader ?? new YamlEntryLoader();
        $this->badges      = $badges ?? new BadgeRenderer();
        $this->sidecarPath = $sidecarPath ?? __DIR__ . '/../../state/enrichment.json';
    }

    public function setFilename(string $filename): void
    {
        $this->filename = $filename;
    }

    public function parseToMarkdown(): string
    {
        $entries = $this->loader->load($this->filename);
        $state   = SidecarState::loadOrEmpty($this->sidecarPath);

        $lines = [];
        foreach ($entries as $entry) {
            $lines[] = $this->formatLine($entry, $state);
        }
        return implode("\n", $lines);
    }

    private function formatLine(Entry $entry, SidecarState $state): string
    {
        $link = $entry->url !== null ? "[{$entry->name}]({$entry->url})" : $entry->name;
        $badges = $entry->url !== null
            ? $this->badges->render($state->signalsFor($entry->url))
            : '';
        $line = "- {$link}{$badges}";
        if ($entry->description !== null && $entry->description !== '') {
            $line .= " - {$entry->description}";
        }
        return $line;
    }
}
