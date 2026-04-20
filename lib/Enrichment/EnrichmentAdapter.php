<?php declare(strict_types=1);
namespace AwesomeList\Enrichment;

use AwesomeList\Entry;

interface EnrichmentAdapter
{
    /** Returns the `EntryType` string value this adapter handles (e.g. 'github_repo'). */
    public function type(): string;

    /**
     * Fetches fresh signals for the entry. Implementations must be deterministic in tests.
     *
     * $priorState is the previous per-URL sidecar block (empty array if never enriched),
     * allowing adapters to detect transitions and carry values like link_status_since.
     *
     * @param array<string, mixed> $priorState
     */
    public function enrich(Entry $entry, array $priorState): EnrichmentResult;
}
