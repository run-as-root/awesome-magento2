<?php declare(strict_types=1);
namespace AwesomeList\Tests;

use AwesomeList\Entry;
use AwesomeList\EntryType;
use PHPUnit\Framework\TestCase;
use InvalidArgumentException;

final class EntryTest extends TestCase
{
    public function test_it_constructs_with_required_fields(): void
    {
        $entry = new Entry(
            name: 'n98-magerun2',
            url: 'https://github.com/netz98/n98-magerun2',
            description: 'Swiss Army Knife',
            type: EntryType::GithubRepo,
            added: '2018-03-15',
        );

        $this->assertSame('n98-magerun2', $entry->name);
        $this->assertSame(EntryType::GithubRepo, $entry->type);
        $this->assertFalse($entry->pinned);
    }

    public function test_it_rejects_empty_name(): void
    {
        $this->expectException(InvalidArgumentException::class);
        new Entry(name: '', url: 'https://x', description: 'y', type: EntryType::Canonical, added: '2020-01-01');
    }

    public function test_it_allows_null_url_for_archive_type(): void
    {
        $entry = new Entry(
            name: 'Vinai Kopp',
            url: null,
            description: null,
            type: EntryType::Archive,
            added: '2017-01-01',
        );
        $this->assertNull($entry->url);
    }

    public function test_it_rejects_null_url_for_non_archive_type(): void
    {
        $this->expectException(InvalidArgumentException::class);
        new Entry(name: 'Foo', url: null, description: null, type: EntryType::Blog, added: '2020-01-01');
    }
}
