<?php declare(strict_types=1);
namespace AwesomeList\Tests\Discovery;

use AwesomeList\Discovery\EntryAppender;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Yaml\Yaml;

final class EntryAppenderTest extends TestCase
{
    private string $tmp;

    protected function setUp(): void
    {
        $this->tmp = tempnam(sys_get_temp_dir(), 'appender-') . '.yml';
    }

    protected function tearDown(): void
    {
        if (file_exists($this->tmp)) {
            unlink($this->tmp);
        }
    }

    public function test_creates_file_when_missing(): void
    {
        (new EntryAppender())->append($this->tmp, [
            'name'        => 'Alpha Beta',
            'url'         => 'https://github.com/alpha/beta',
            'description' => 'A thing.',
            'type'        => 'github_repo',
            'added'       => '2026-04-20',
        ]);

        $parsed = Yaml::parseFile($this->tmp);
        $this->assertCount(1, $parsed);
        $this->assertSame('Alpha Beta', $parsed[0]['name']);
        $this->assertSame('2026-04-20', $parsed[0]['added']);
    }

    public function test_appends_to_existing_file_preserving_prior_entries(): void
    {
        file_put_contents($this->tmp, "- name: First\n  url: https://example.com\n  description: original\n  type: vendor_site\n  added: \"2024-01-01\"\n");
        (new EntryAppender())->append($this->tmp, [
            'name'        => 'Second',
            'url'         => 'https://github.com/owner/second',
            'description' => 'new entry',
            'type'        => 'github_repo',
            'added'       => '2026-04-20',
        ]);

        $parsed = Yaml::parseFile($this->tmp);
        $this->assertCount(2, $parsed);
        $this->assertSame('First', $parsed[0]['name']);
        $this->assertSame('Second', $parsed[1]['name']);
    }

    public function test_quotes_description_when_it_contains_special_chars(): void
    {
        (new EntryAppender())->append($this->tmp, [
            'name'        => 'X',
            'url'         => 'https://example.com',
            'description' => 'A: colon-bearing description with "quotes" too',
            'type'        => 'vendor_site',
            'added'       => '2026-04-20',
        ]);

        $parsed = Yaml::parseFile($this->tmp);
        $this->assertSame('A: colon-bearing description with "quotes" too', $parsed[0]['description']);
    }

    public function test_no_trailing_newline_on_existing_file_gets_normalised(): void
    {
        file_put_contents($this->tmp, "- name: First\n  url: https://example.com\n  description: x\n  type: vendor_site\n  added: \"2024-01-01\"");
        // note: no trailing newline on existing content
        (new EntryAppender())->append($this->tmp, [
            'name'        => 'Second',
            'url'         => 'https://github.com/owner/second',
            'description' => 'new',
            'type'        => 'github_repo',
            'added'       => '2026-04-20',
        ]);

        $parsed = Yaml::parseFile($this->tmp);
        $this->assertCount(2, $parsed);
    }
}
