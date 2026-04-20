<?php declare(strict_types=1);
namespace AwesomeList\Tests\Rendering;

use AwesomeList\Rendering\Stats;
use AwesomeList\SidecarState;
use AwesomeList\YamlEntryLoader;
use PHPUnit\Framework\TestCase;

final class StatsTest extends TestCase
{
    public function test_counts_total_maintained_hot_graveyard(): void
    {
        $dataDir  = sys_get_temp_dir() . '/stats-data-' . uniqid();
        $statePath = tempnam(sys_get_temp_dir(), 'stats-state-') . '.json';
        mkdir($dataDir . '/sub', 0755, true);

        file_put_contents($dataDir . '/a.yml', <<<YML
- name: Alpha
  url: https://example.com/alpha
  description: hot + maintained
  type: github_repo
  added: "2020-01-01"
- name: Beta
  url: https://example.com/beta
  description: only maintained
  type: github_repo
  added: "2020-01-01"
YML);
        file_put_contents($dataDir . '/sub/b.yml', <<<YML
- name: Gamma
  url: https://example.com/gamma
  description: graveyard
  type: github_repo
  added: "2020-01-01"
- name: Canonical
  url: https://example.com/canonical
  description: pinned, graveyard signal ignored
  type: canonical
  added: "2020-01-01"
  pinned: true
  pin_reason: canonical
YML);

        file_put_contents($statePath, json_encode([
            'https://example.com/alpha'     => ['signals' => ['vitality_hot' => true, 'actively_maintained' => true]],
            'https://example.com/beta'      => ['signals' => ['actively_maintained' => true]],
            'https://example.com/gamma'     => ['signals' => ['graveyard_candidate' => true]],
            'https://example.com/canonical' => ['signals' => ['graveyard_candidate' => true]],
        ]));

        $stats = (new Stats(new YamlEntryLoader()))->collect($dataDir, SidecarState::loadOrEmpty($statePath));

        $this->assertSame(['total' => 4, 'maintained' => 2, 'hot' => 1, 'graveyard' => 1], $stats);

        foreach (glob($dataDir . '/sub/*') as $f) { unlink($f); }
        foreach (glob($dataDir . '/*.yml') as $f) { unlink($f); }
        rmdir($dataDir . '/sub');
        rmdir($dataDir);
        unlink($statePath);
    }

    public function test_render_produces_a_one_line_blockquote(): void
    {
        $rendered = (new Stats(new YamlEntryLoader()))->render(['total' => 250, 'maintained' => 80, 'hot' => 12, 'graveyard' => 18]);
        $this->assertSame(
            '> Tracking **250** projects · **80** actively maintained · **12** 🔥 hot · **18** 🪦 on the graveyard shelf.',
            $rendered,
        );
    }
}
