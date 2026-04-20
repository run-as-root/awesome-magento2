<?php declare(strict_types=1);
namespace AwesomeList\Rendering;

use AwesomeList\Entry;
use AwesomeList\SidecarState;

final class EntrySorter
{
    private const RANK_PINNED     = 0;
    private const RANK_HOT        = 1;
    private const RANK_MAINTAINED = 2;
    private const RANK_OTHER      = 3;

    /**
     * Sort entries within a section so the best candidates float to the top:
     *   pinned first, then top-10% (hot) + maintained, then maintained alone,
     *   then everything else. Ties broken by stars / monthly downloads desc, then name.
     *
     * @param Entry[] $entries
     * @return Entry[]
     */
    public function sort(array $entries, SidecarState $state): array
    {
        $indexed = [];
        foreach ($entries as $i => $entry) {
            $signals = $entry->url !== null ? ($state->signalsFor($entry->url) ?? []) : [];
            $indexed[] = [
                'entry'  => $entry,
                'rank'   => $this->rank($entry, $signals),
                'score'  => $this->score($entry->url, $state),
                'name'   => strtolower($entry->name),
                'input'  => $i,
            ];
        }
        usort($indexed, static function (array $a, array $b): int {
            return $a['rank']  <=> $b['rank']
                ?: $b['score'] <=> $a['score']
                ?: $a['name']  <=> $b['name']
                ?: $a['input'] <=> $b['input'];
        });
        return array_column($indexed, 'entry');
    }

    /** @param array<string,mixed> $signals */
    private function rank(Entry $entry, array $signals): int
    {
        if ($entry->pinned) {
            return self::RANK_PINNED;
        }
        if (!empty($signals['vitality_hot'])) {
            return self::RANK_HOT;
        }
        if (!empty($signals['actively_maintained'])) {
            return self::RANK_MAINTAINED;
        }
        return self::RANK_OTHER;
    }

    private function score(?string $url, SidecarState $state): int
    {
        if ($url === null) {
            return 0;
        }
        $record = $state->forUrl($url);
        return (int) ($record['github']['stars']
            ?? $record['packagist']['downloads_monthly']
            ?? 0);
    }
}
