<?php declare(strict_types=1);
namespace AwesomeList\Tests\Enrichment;

use AwesomeList\Entry;
use AwesomeList\Enrichment\PodcastAdapter;
use AwesomeList\EntryType;
use GuzzleHttp\Client;
use GuzzleHttp\Handler\MockHandler;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Psr7\Response;
use PHPUnit\Framework\TestCase;

final class PodcastAdapterTest extends TestCase
{
    public function test_active_feed_produces_actively_maintained(): void
    {
        $html = (string) file_get_contents(__DIR__ . '/../fixtures/http/podcast/html-with-rss-link.html');
        $feed = (string) file_get_contents(__DIR__ . '/../fixtures/http/podcast/feed-active.xml');
        $adapter = $this->build([
            new Response(200, [], $html),
            new Response(200, [], $feed),
        ], '2026-04-20T00:00:00Z');

        $result = $adapter->enrich($this->entry('https://example.com/'), []);

        $this->assertTrue($result->signals['actively_maintained']);
        $this->assertFalse($result->signals['graveyard_candidate']);
        $this->assertSame('https://example.com/feed', $result->typeData['podcast']['feed_url']);
        $this->assertStringStartsWith('2026-04-10', $result->typeData['podcast']['last_episode']);
    }

    public function test_stale_feed_is_graveyard(): void
    {
        $html = (string) file_get_contents(__DIR__ . '/../fixtures/http/podcast/html-with-rss-link.html');
        $feed = (string) file_get_contents(__DIR__ . '/../fixtures/http/podcast/feed-stale.xml');
        $adapter = $this->build([
            new Response(200, [], $html),
            new Response(200, [], $feed),
        ], '2026-04-20T00:00:00Z');

        $result = $adapter->enrich($this->entry('https://example.com/'), []);

        $this->assertFalse($result->signals['actively_maintained']);
        $this->assertTrue($result->signals['graveyard_candidate']);
    }

    public function test_missing_feed_link_falls_back_to_common_paths(): void
    {
        $html = (string) file_get_contents(__DIR__ . '/../fixtures/http/podcast/html-no-rss-link.html');
        $feed = (string) file_get_contents(__DIR__ . '/../fixtures/http/podcast/feed-active.xml');
        $adapter = $this->build([
            new Response(200, [], $html),  // root fetch
            new Response(404),              // /feed
            new Response(200, [], $feed),   // /feed/ (HEAD-like)
            new Response(200, [], $feed),   // /feed/ (body fetch)
        ], '2026-04-20T00:00:00Z');

        $result = $adapter->enrich($this->entry('https://example.com/'), []);

        $this->assertTrue($result->signals['actively_maintained']);
        $this->assertSame('https://example.com/feed/', $result->typeData['podcast']['feed_url']);
    }

    public function test_unreachable_host_is_graveyard(): void
    {
        $adapter = $this->build([new Response(404)], '2026-04-20T00:00:00Z');
        $result  = $adapter->enrich($this->entry('https://dead.example/'), []);

        $this->assertTrue($result->signals['graveyard_candidate']);
        $this->assertNull($result->typeData['podcast']['last_episode']);
    }

    public function test_type_returns_podcast(): void
    {
        $this->assertSame('podcast', (new PodcastAdapter(new Client(), new \DateTimeImmutable()))->type());
    }

    private function build(array $responses, string $nowIso): PodcastAdapter
    {
        $mock   = new MockHandler($responses);
        $client = new Client(['handler' => HandlerStack::create($mock), 'http_errors' => false]);
        return new PodcastAdapter($client, new \DateTimeImmutable($nowIso));
    }

    private function entry(string $url): Entry
    {
        return new Entry(
            name: 'test-podcast',
            url: $url,
            description: null,
            type: EntryType::Podcast,
            added: '2020-01-01',
        );
    }
}
