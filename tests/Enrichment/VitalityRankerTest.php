<?php declare(strict_types=1);
namespace AwesomeList\Tests\Enrichment;

use AwesomeList\Enrichment\EnrichmentResult;
use AwesomeList\Enrichment\VitalityRanker;
use PHPUnit\Framework\TestCase;

final class VitalityRankerTest extends TestCase
{
    public function test_it_marks_top_decile_when_category_has_enough_entries(): void
    {
        $results = [];
        for ($i = 1; $i <= 10; $i++) {
            $results["url-$i"] = [
                'category' => 'tools',
                'result'   => $this->withStars($i * 100),
            ];
        }

        $ranked = (new VitalityRanker())->rank($results);

        // Top 10% of 10 = 1 entry → the 1000-star one (url-10) is hot.
        $this->assertTrue($ranked['url-10']['result']->signals['vitality_hot']);
        $this->assertFalse($ranked['url-9']['result']->signals['vitality_hot']);
        $this->assertFalse($ranked['url-1']['result']->signals['vitality_hot']);
    }

    public function test_it_marks_nothing_when_category_is_too_small(): void
    {
        $results = [
            'a' => ['category' => 'tiny', 'result' => $this->withStars(5000)],
            'b' => ['category' => 'tiny', 'result' => $this->withStars(10)],
        ];

        $ranked = (new VitalityRanker())->rank($results);

        $this->assertFalse($ranked['a']['result']->signals['vitality_hot']);
        $this->assertFalse($ranked['b']['result']->signals['vitality_hot']);
    }

    public function test_it_ignores_non_github_results(): void
    {
        $results = [
            'gh'   => ['category' => 'mixed', 'result' => $this->withStars(9999)],
            'blog' => ['category' => 'mixed', 'result' => new EnrichmentResult('2026-01-01T00:00:00Z', ['vitality_hot' => false])],
        ];

        $ranked = (new VitalityRanker())->rank($results);

        $this->assertFalse($ranked['gh']['result']->signals['vitality_hot']); // 1-entry category after filtering, under threshold
        $this->assertArrayNotHasKey('github', $ranked['blog']['result']->typeData);
    }

    public function test_it_marks_top_decile_for_packagist_by_monthly_downloads(): void
    {
        $results = [];
        for ($i = 1; $i <= 10; $i++) {
            $results["url-$i"] = [
                'category' => 'extensions-marketing',
                'result'   => new EnrichmentResult(
                    '2026-04-20T00:00:00Z',
                    ['vitality_hot' => false],
                    ['packagist' => ['downloads_monthly' => $i * 100]],
                ),
            ];
        }
        $ranked = (new VitalityRanker())->rank($results);
        $this->assertTrue($ranked['url-10']['result']->signals['vitality_hot']);
        $this->assertFalse($ranked['url-1']['result']->signals['vitality_hot']);
    }

    private function withStars(int $stars): EnrichmentResult
    {
        return new EnrichmentResult(
            lastChecked: '2026-04-19T02:00:00Z',
            signals: ['vitality_hot' => false, 'actively_maintained' => true, 'graveyard_candidate' => false],
            typeData: ['github' => ['stars' => $stars, 'last_commit' => null, 'last_release' => null, 'archived' => false, 'fork' => false]],
        );
    }
}
