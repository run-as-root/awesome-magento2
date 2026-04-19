<?php declare(strict_types=1);
namespace AwesomeList\Enrichment;

use AwesomeList\Entry;

interface EnrichmentAdapter
{
    /** Returns the `EntryType` string value this adapter handles (e.g. 'github_repo'). */
    public function type(): string;

    /** Fetches fresh signals for the entry. Implementations must be deterministic in tests. */
    public function enrich(Entry $entry): EnrichmentResult;
}
