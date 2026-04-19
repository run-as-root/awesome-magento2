<?php declare(strict_types=1);
namespace AwesomeList\Enrichment;

use AwesomeList\EntryType;

final class AdapterFactory
{
    /** @var array<string, EnrichmentAdapter> */
    private readonly array $byType;

    /** @param EnrichmentAdapter[] $adapters */
    public function __construct(array $adapters)
    {
        $map = [];
        foreach ($adapters as $adapter) {
            $map[$adapter->type()] = $adapter;
        }
        $this->byType = $map;
    }

    public function for(EntryType $type): ?EnrichmentAdapter
    {
        return $this->byType[$type->value] ?? null;
    }
}
