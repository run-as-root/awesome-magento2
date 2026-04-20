<?php declare(strict_types=1);
namespace AwesomeList\Tests\Discovery;

use AwesomeList\Discovery\EntryRemover;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Yaml\Yaml;

final class EntryRemoverTest extends TestCase
{
    private string $tmp;

    protected function setUp(): void
    {
        $this->tmp = tempnam(sys_get_temp_dir(), 'remover-') . '.yml';
    }

    protected function tearDown(): void
    {
        if (file_exists($this->tmp)) {
            unlink($this->tmp);
        }
    }

    public function test_removes_matching_entry_and_preserves_the_rest(): void
    {
        file_put_contents($this->tmp, <<<YML
- name: Alpha
  url: https://example.com/alpha
  description: keep
  type: vendor_site
  added: "2020-01-01"
- name: Beta
  url: https://example.com/beta
  description: delete this one
  type: vendor_site
  added: "2020-01-01"
- name: Gamma
  url: https://example.com/gamma
  description: also keep
  type: vendor_site
  added: "2020-01-01"
YML);

        $removed = (new EntryRemover())->remove($this->tmp, 'https://example.com/beta');

        $this->assertTrue($removed);
        $parsed = Yaml::parseFile($this->tmp);
        $this->assertCount(2, $parsed);
        $this->assertSame('Alpha', $parsed[0]['name']);
        $this->assertSame('Gamma', $parsed[1]['name']);
    }

    public function test_removes_first_entry(): void
    {
        file_put_contents($this->tmp, <<<YML
- name: First
  url: https://x/first
  description: x
  type: vendor_site
  added: "2020-01-01"
- name: Second
  url: https://x/second
  description: x
  type: vendor_site
  added: "2020-01-01"
YML);

        (new EntryRemover())->remove($this->tmp, 'https://x/first');

        $parsed = Yaml::parseFile($this->tmp);
        $this->assertCount(1, $parsed);
        $this->assertSame('Second', $parsed[0]['name']);
    }

    public function test_removes_last_entry(): void
    {
        file_put_contents($this->tmp, <<<YML
- name: First
  url: https://x/first
  description: x
  type: vendor_site
  added: "2020-01-01"
- name: Last
  url: https://x/last
  description: x
  type: vendor_site
  added: "2020-01-01"
YML);

        (new EntryRemover())->remove($this->tmp, 'https://x/last');

        $parsed = Yaml::parseFile($this->tmp);
        $this->assertCount(1, $parsed);
        $this->assertSame('First', $parsed[0]['name']);
    }

    public function test_returns_false_when_url_not_found(): void
    {
        file_put_contents($this->tmp, <<<YML
- name: Only
  url: https://x/only
  description: x
  type: vendor_site
  added: "2020-01-01"
YML);

        $removed = (new EntryRemover())->remove($this->tmp, 'https://x/missing');

        $this->assertFalse($removed);
        $parsed = Yaml::parseFile($this->tmp);
        $this->assertCount(1, $parsed);
    }

    public function test_returns_false_when_file_missing(): void
    {
        $this->assertFalse((new EntryRemover())->remove('/nonexistent/path.yml', 'https://x/y'));
    }
}
