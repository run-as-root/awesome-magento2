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

        $active    = [];
        $graveyard = [];
        foreach ($entries as $entry) {
            $signals = $entry->url !== null ? ($state->signalsFor($entry->url) ?? []) : [];
            $isGraveyard = !$entry->pinned && !empty($signals['graveyard_candidate']);
            if ($isGraveyard) {
                $graveyard[] = $this->formatLine($entry, $state);
            } else {
                $active[] = $this->formatLine($entry, $state);
            }
        }

        $out = implode("\n", $active);
        if ($graveyard !== []) {
            $out .= "\n\n<details>\n<summary>🪦 Graveyard — projects no longer recommended</summary>\n\n"
                 . implode("\n", $graveyard)
                 . "\n\n</details>";
        }
        return $out;
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
