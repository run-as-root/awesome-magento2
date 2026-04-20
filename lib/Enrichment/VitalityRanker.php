<?php declare(strict_types=1);
namespace AwesomeList\Enrichment;

final class VitalityRanker
{
    private const MIN_CATEGORY_SIZE = 5;
    private const TOP_DECILE        = 0.10;

    /**
     * @param array<string, array{category: string, result: EnrichmentResult}> $results
     * @return array<string, array{category: string, result: EnrichmentResult}>
     */
    public function rank(array $results): array
    {
        $buckets = [];
        foreach ($results as $url => $row) {
            $td = $row['result']->typeData;
            if (isset($td['github'])) {
                $buckets[$row['category'] . ':github'][$url] = $td['github']['stars'] ?? 0;
            } elseif (isset($td['packagist'])) {
                $buckets[$row['category'] . ':packagist'][$url] = $td['packagist']['downloads_monthly'] ?? 0;
            }
        }

        $hotUrls = [];
        foreach ($buckets as $stars) {
            $count = count($stars);
            if ($count < self::MIN_CATEGORY_SIZE) {
                continue;
            }
            arsort($stars);
            $cutoff = max(1, (int) floor($count * self::TOP_DECILE));
            $hotUrls = array_merge($hotUrls, array_slice(array_keys($stars), 0, $cutoff));
        }

        $hotSet = array_flip($hotUrls);
        foreach ($results as $url => $row) {
            $signals = $row['result']->signals;
            $signals['vitality_hot'] = isset($hotSet[$url]);
            $results[$url]['result'] = new EnrichmentResult(
                lastChecked: $row['result']->lastChecked,
                signals: $signals,
                typeData: $row['result']->typeData,
            );
        }
        return $results;
    }
}
