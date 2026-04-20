<?php declare(strict_types=1);
namespace AwesomeList\Tests\Enrichment;

use AwesomeList\Entry;
use AwesomeList\Enrichment\PackagistAdapter;
use AwesomeList\EntryType;
use GuzzleHttp\Client;
use GuzzleHttp\Handler\MockHandler;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Psr7\Response;
use PHPUnit\Framework\TestCase;

final class PackagistAdapterTest extends TestCase
{
    public function test_active_package_is_actively_maintained(): void
    {
        $body    = (string) file_get_contents(__DIR__ . '/../fixtures/http/packagist/pkg-active.json');
        $adapter = $this->build([new Response(200, [], $body)], '2026-04-20T00:00:00Z');
        $result  = $adapter->enrich($this->entry('https://packagist.org/packages/magepal/magento2-google-tag-manager'), []);

        $this->assertTrue($result->signals['actively_maintained']);
        $this->assertFalse($result->signals['graveyard_candidate']);
        $this->assertSame(50000, $result->typeData['packagist']['downloads_total']);
        $this->assertSame(1200, $result->typeData['packagist']['downloads_monthly']);
        $this->assertSame('2025-11-10T12:00:00+00:00', $result->typeData['packagist']['last_release']);
        $this->assertFalse($result->typeData['packagist']['abandoned']);
    }

    public function test_abandoned_package_is_graveyard(): void
    {
        $body    = (string) file_get_contents(__DIR__ . '/../fixtures/http/packagist/pkg-abandoned.json');
        $adapter = $this->build([new Response(200, [], $body)], '2026-04-20T00:00:00Z');
        $result  = $adapter->enrich($this->entry('https://packagist.org/packages/someone/defunct'), []);

        $this->assertTrue($result->signals['graveyard_candidate']);
        $this->assertFalse($result->signals['actively_maintained']);
        $this->assertTrue($result->typeData['packagist']['abandoned']);
    }

    public function test_404_is_graveyard(): void
    {
        $adapter = $this->build([new Response(404)], '2026-04-20T00:00:00Z');
        $result  = $adapter->enrich($this->entry('https://packagist.org/packages/ghost/package'), []);

        $this->assertTrue($result->signals['graveyard_candidate']);
        $this->assertNull($result->typeData['packagist']['last_release']);
    }

    public function test_type_returns_packagist_pkg(): void
    {
        $this->assertSame('packagist_pkg', (new PackagistAdapter(new Client(), new \DateTimeImmutable()))->type());
    }

    private function build(array $responses, string $nowIso): PackagistAdapter
    {
        $mock   = new MockHandler($responses);
        $client = new Client(['handler' => HandlerStack::create($mock)]);
        return new PackagistAdapter($client, new \DateTimeImmutable($nowIso));
    }

    private function entry(string $url): Entry
    {
        return new Entry(
            name: 'test',
            url: $url,
            description: null,
            type: EntryType::PackagistPkg,
            added: '2020-01-01',
        );
    }
}
