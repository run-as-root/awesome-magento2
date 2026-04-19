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
}
