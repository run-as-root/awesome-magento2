<?php declare(strict_types=1);
namespace AwesomeList\Tests\Enrichment;

use AwesomeList\Enrichment\EnrichmentResult;
use PHPUnit\Framework\TestCase;

final class EnrichmentResultTest extends TestCase
{
    public function test_it_serialises_to_sidecar_shape(): void
    {
        $result = new EnrichmentResult(
            lastChecked: '2026-04-19T02:00:00Z',
            signals: ['actively_maintained' => true, 'graveyard_candidate' => false],
            typeData: ['github' => ['stars' => 42, 'archived' => false]],
        );

        $this->assertSame([
            'last_checked' => '2026-04-19T02:00:00Z',
            'github'       => ['stars' => 42, 'archived' => false],
            'signals'      => ['actively_maintained' => true, 'graveyard_candidate' => false],
        ], $result->toArray());
    }

    public function test_it_omits_type_data_when_empty(): void
    {
        $result = new EnrichmentResult(
            lastChecked: '2026-04-19T02:00:00Z',
            signals: ['vitality_hot' => false],
            typeData: [],
        );

        $this->assertSame([
            'last_checked' => '2026-04-19T02:00:00Z',
            'signals'      => ['vitality_hot' => false],
        ], $result->toArray());
    }
}
