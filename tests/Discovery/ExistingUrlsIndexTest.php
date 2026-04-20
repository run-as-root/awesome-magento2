<?php declare(strict_types=1);
namespace AwesomeList\Tests\Discovery;

use AwesomeList\Discovery\ExistingUrlsIndex;
use PHPUnit\Framework\TestCase;

final class ExistingUrlsIndexTest extends TestCase
{
    public function test_contains_urls_from_every_yaml_under_data(): void
    {
        $idx = ExistingUrlsIndex::build(__DIR__ . '/../fixtures/enrichment/data');
        $this->assertTrue($idx->contains('https://github.com/netz98/n98-magerun2'));
        $this->assertTrue($idx->contains('https://hyva.io/'));
        $this->assertFalse($idx->contains('https://github.com/ghost/never-heard-of-it'));
    }

    public function test_normalises_github_url_variants(): void
    {
        $idx = ExistingUrlsIndex::build(__DIR__ . '/../fixtures/enrichment/data');
        // Trailing slash, .git suffix, www. prefix all collapse to the same canonical key.
        $this->assertTrue($idx->contains('https://github.com/netz98/n98-magerun2/'));
        $this->assertTrue($idx->contains('https://github.com/netz98/n98-magerun2.git'));
        $this->assertTrue($idx->contains('https://www.github.com/netz98/n98-magerun2'));
    }
}
