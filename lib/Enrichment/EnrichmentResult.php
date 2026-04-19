<?php declare(strict_types=1);
namespace AwesomeList\Enrichment;

final class EnrichmentResult
{
    public function __construct(
        public readonly string $lastChecked,
        public readonly array $signals,
        public readonly array $typeData = [],
    ) {}

    public function toArray(): array
    {
        $out = ['last_checked' => $this->lastChecked];
        foreach ($this->typeData as $key => $block) {
            $out[$key] = $block;
        }
        $out['signals'] = $this->signals;
        return $out;
    }
}
