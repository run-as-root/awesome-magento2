<?php declare(strict_types=1);
namespace AwesomeList\Tests\Enrichment;

use AwesomeList\Entry;
use AwesomeList\Enrichment\ArchiveAdapter;
use AwesomeList\EntryType;
use PHPUnit\Framework\TestCase;

final class ArchiveAdapterTest extends TestCase
{
    public function test_it_returns_last_checked_with_no_signals(): void
    {
        $adapter = new ArchiveAdapter(new \DateTimeImmutable('2026-04-20T02:00:00Z'));
        $entry   = new Entry(
            name: 'Vinai Kopp',
            url: null,
            description: 'Community member',
            type: EntryType::Archive,
            added: '2017-01-01',
        );

        $result = $adapter->enrich($entry, []);

        $this->assertSame('2026-04-20T02:00:00Z', $result->lastChecked);
        $this->assertSame([], $result->signals);
        $this->assertSame([], $result->typeData);
    }

    public function test_type_returns_archive(): void
    {
        $this->assertSame('archive', (new ArchiveAdapter(new \DateTimeImmutable()))->type());
    }
}
