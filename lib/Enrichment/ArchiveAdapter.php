<?php declare(strict_types=1);
namespace AwesomeList\Enrichment;

use AwesomeList\Entry;
use DateTimeImmutable;

final class ArchiveAdapter implements EnrichmentAdapter
{
    public function __construct(private readonly DateTimeImmutable $now) {}

    public function type(): string
    {
        return 'archive';
    }

    public function enrich(Entry $entry, array $priorState): EnrichmentResult
    {
        return new EnrichmentResult(
            lastChecked: $this->now->format('Y-m-d\TH:i:s\Z'),
            signals: [],
        );
    }
}
