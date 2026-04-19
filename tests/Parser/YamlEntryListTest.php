<?php declare(strict_types=1);
namespace AwesomeList\Tests\Parser;

use AwesomeList\Parser\YamlEntryList;
use PHPUnit\Framework\TestCase;

final class YamlEntryListTest extends TestCase
{
    public function test_it_emits_one_markdown_line_per_entry(): void
    {
        $parser = new YamlEntryList();
        $parser->setFilename(__DIR__ . '/../fixtures/entries/sample.yml');
        $markdown = $parser->parseToMarkdown();

        $this->assertStringContainsString(
            '- [n98-magerun2](https://github.com/netz98/n98-magerun2) - The CLI Swiss Army Knife for Magento 2.',
            $markdown,
        );
        $this->assertStringContainsString(
            '- [Magento Developer Documentation](http://devdocs.magento.com/) - Official Developer Documentation.',
            $markdown,
        );
    }

    public function test_entries_without_description_omit_the_dash(): void
    {
        $parser = new YamlEntryList();
        $parser->setFilename(__DIR__ . '/../fixtures/entries/no-description.yml');
        $markdown = $parser->parseToMarkdown();
        $this->assertStringContainsString('- [Foo](https://foo.example)' . "\n", $markdown);
    }

    public function test_graveyard_entries_are_routed_to_a_details_block(): void
    {
        $parser = new YamlEntryList(sidecarPath: __DIR__ . '/../fixtures/state/graveyard.json');
        $parser->setFilename(__DIR__ . '/../fixtures/entries/with-graveyard.yml');
        $md = $parser->parseToMarkdown();

        $this->assertStringContainsString('- [Active](https://github.com/org/active) - Still going.', $md);
        $this->assertStringContainsString('- [Canonical-Pinned](https://github.com/org/pinned)', $md);
        $this->assertStringContainsString('<details>', $md);
        $this->assertStringContainsString('<summary>🪦 Graveyard', $md);
        $this->assertStringContainsString('- [Dead](https://github.com/org/dead) - Archived.', $md);

        $this->assertLessThan(
            strpos($md, '<details>'),
            strpos($md, 'Active'),
        );
    }
}
