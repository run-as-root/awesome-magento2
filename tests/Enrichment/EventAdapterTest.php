<?php declare(strict_types=1);
namespace AwesomeList\Tests\Enrichment;

use AwesomeList\Entry;
use AwesomeList\Enrichment\EventAdapter;
use AwesomeList\EntryType;
use GuzzleHttp\Client;
use GuzzleHttp\Handler\MockHandler;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Psr7\Response;
use PHPUnit\Framework\TestCase;

final class EventAdapterTest extends TestCase
{
    public function test_current_year_page_is_actively_maintained(): void
    {
        $body = (string) file_get_contents(__DIR__ . '/../fixtures/http/event/page-current.html');
        $adapter = $this->build([new Response(200, [], $body)], '2026-04-20T00:00:00Z');
        $result  = $adapter->enrich($this->entry('https://example.com/'), []);

        $this->assertTrue($result->signals['actively_maintained']);
        $this->assertFalse($result->signals['graveyard_candidate']);
        $this->assertSame(2026, $result->typeData['event']['latest_year_on_page']);
    }

    public function test_page_with_only_old_years_is_not_active(): void
    {
        $body = (string) file_get_contents(__DIR__ . '/../fixtures/http/event/page-old.html');
        $adapter = $this->build([new Response(200, [], $body)], '2026-04-20T00:00:00Z');
        $result  = $adapter->enrich($this->entry('https://example.com/'), []);

        $this->assertFalse($result->signals['actively_maintained']);
        $this->assertSame(2019, $result->typeData['event']['latest_year_on_page']);
    }

    public function test_404_is_graveyard(): void
    {
        $adapter = $this->build([new Response(404)], '2026-04-20T00:00:00Z');
        $result  = $adapter->enrich($this->entry('https://dead.example/'), []);

        $this->assertTrue($result->signals['graveyard_candidate']);
    }

    public function test_type_returns_event(): void
    {
        $this->assertSame('event', (new EventAdapter(new Client(), new \DateTimeImmutable()))->type());
    }

    private function build(array $responses, string $nowIso): EventAdapter
    {
        $mock   = new MockHandler($responses);
        $client = new Client(['handler' => HandlerStack::create($mock), 'http_errors' => false]);
        return new EventAdapter($client, new \DateTimeImmutable($nowIso));
    }

    private function entry(string $url): Entry
    {
        return new Entry(
            name: 'test-event',
            url: $url,
            description: null,
            type: EntryType::Event,
            added: '2020-01-01',
        );
    }
}
