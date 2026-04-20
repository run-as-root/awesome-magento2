<?php declare(strict_types=1);
namespace AwesomeList\Rendering;

use AwesomeList\SidecarState;
use AwesomeList\YamlEntryLoader;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;

final class Stats
{
    public function __construct(private readonly YamlEntryLoader $loader) {}

    /** @return array{total: int, maintained: int, hot: int, graveyard: int} */
    public function collect(string $dataDir, SidecarState $state): array
    {
        $total = $maintained = $hot = $graveyard = 0;
        if (!is_dir($dataDir)) {
            return compact('total', 'maintained', 'hot', 'graveyard');
        }
        $it = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($dataDir));
        foreach ($it as $f) {
            if (!$f->isFile() || $f->getExtension() !== 'yml') {
                continue;
            }
            foreach ($this->loader->load($f->getPathname()) as $entry) {
                $total++;
                if ($entry->url === null) {
                    continue;
                }
                $signals = $state->signalsFor($entry->url) ?? [];
                if (!empty($signals['vitality_hot'])) {
                    $hot++;
                }
                if (!empty($signals['actively_maintained'])) {
                    $maintained++;
                }
                if (!$entry->pinned && !empty($signals['graveyard_candidate'])) {
                    $graveyard++;
                }
            }
        }
        return compact('total', 'maintained', 'hot', 'graveyard');
    }

    /** @param array{total: int, maintained: int, hot: int, graveyard: int} $stats */
    public function render(array $stats): string
    {
        return sprintf(
            '> Tracking **%d** projects · **%d** actively maintained · **%d** 🔥 hot · **%d** 🪦 on the graveyard shelf.',
            $stats['total'],
            $stats['maintained'],
            $stats['hot'],
            $stats['graveyard'],
        );
    }
}
