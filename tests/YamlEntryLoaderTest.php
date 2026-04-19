<?php declare(strict_types=1);
namespace AwesomeList\Tests;

use AwesomeList\EntryType;
use AwesomeList\YamlEntryLoader;
use PHPUnit\Framework\TestCase;
use RuntimeException;

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

    public function test_it_throws_when_file_is_missing(): void
    {
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessageMatches('/YAML file not found/');

        (new YamlEntryLoader())->load(__DIR__ . '/fixtures/entries/does-not-exist.yml');
    }

    public function test_it_throws_when_root_is_not_a_list(): void
    {
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessageMatches('/Expected a list at the root/');

        (new YamlEntryLoader())->load(__DIR__ . '/fixtures/entries/invalid/root-not-list.yml');
    }

    public function test_it_throws_when_row_is_not_a_mapping(): void
    {
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessageMatches('/not a mapping/');

        (new YamlEntryLoader())->load(__DIR__ . '/fixtures/entries/invalid/row-not-mapping.yml');
    }

    public function test_it_throws_when_row_is_missing_required_field(): void
    {
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessageMatches("/missing required field 'name'/");

        (new YamlEntryLoader())->load(__DIR__ . '/fixtures/entries/invalid/missing-name.yml');
    }

    public function test_it_throws_when_added_is_unquoted_date(): void
    {
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessageMatches('/added.*quoted/');

        (new YamlEntryLoader())->load(__DIR__ . '/fixtures/entries/invalid/unquoted-date.yml');
    }
}
