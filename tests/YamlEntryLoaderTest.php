<?php declare(strict_types=1);
namespace AwesomeList\Tests;

use AwesomeList\EntryType;
use AwesomeList\YamlEntryLoader;
use PHPUnit\Framework\TestCase;

final class YamlEntryLoaderTest extends TestCase
{
    public function test_it_loads_entries_from_a_yaml_file(): void
    {
        $entries = (new YamlEntryLoader())->load(__DIR__ . '/fixtures/entries/sample.yml');

        $this->assertCount(2, $entries);
        $this->assertSame('n98-magerun2', $entries[0]->name);
        $this->assertSame(EntryType::GithubRepo, $entries[0]->type);
        $this->assertTrue($entries[1]->pinned);
        $this->assertSame('Canonical Adobe resource — never auto-retires.', $entries[1]->pinReason);
    }
}
