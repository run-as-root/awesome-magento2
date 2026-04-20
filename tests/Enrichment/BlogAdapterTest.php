<?php declare(strict_types=1);
namespace AwesomeList\Tests\Enrichment;

use AwesomeList\Entry;
use AwesomeList\Enrichment\BlogAdapter;
use AwesomeList\EntryType;
use GuzzleHttp\Client;
use GuzzleHttp\Handler\MockHandler;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Psr7\Response;
use PHPUnit\Framework\TestCase;

final class BlogAdapterTest extends TestCase
{
    public function test_active_feed_produces_actively_maintained(): void
    {
        $html = (string) file_get_contents(__DIR__ . '/../fixtures/http/blog/html-with-rss-link.html');
        $feed = (string) file_get_contents(__DIR__ . '/../fixtures/http/blog/feed-active.xml');
        $adapter = $this->build([
            new Response(200, [], $html),
            new Response(200, [], $feed),
        ], '2026-04-20T00:00:00Z');

        $result = $adapter->enrich($this->entry('https://example.com/'), []);

        $this->assertTrue($result->signals['actively_maintained']);
        $this->assertFalse($result->signals['graveyard_candidate']);
        $this->assertSame('https://example.com/feed', $result->typeData['blog']['feed_url']);
        $this->assertStringStartsWith('2026-04-10', $result->typeData['blog']['last_post']);
    }

    public function test_stale_feed_is_graveyard(): void
    {
        $html = (string) file_get_contents(__DIR__ . '/../fixtures/http/blog/html-with-rss-link.html');
        $feed = (string) file_get_contents(__DIR__ . '/../fixtures/http/blog/feed-stale.xml');
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
        $html = (string) file_get_contents(__DIR__ . '/../fixtures/http/blog/html-no-rss-link.html');
        $feed = (string) file_get_contents(__DIR__ . '/../fixtures/http/blog/feed-active.xml');
        $adapter = $this->build([
            new Response(200, [], $html),  // root fetch
            new Response(404),              // /feed
            new Response(200, [], $feed),   // /feed/  (200 HEAD-like)
            new Response(200, [], $feed),   // /feed/ again (actual body fetch for latestPostFrom)
        ], '2026-04-20T00:00:00Z');

        $result = $adapter->enrich($this->entry('https://example.com/'), []);

        $this->assertTrue($result->signals['actively_maintained']);
        $this->assertSame('https://example.com/feed/', $result->typeData['blog']['feed_url']);
    }

    public function test_unreachable_host_is_graveyard(): void
    {
        $adapter = $this->build([new Response(404)], '2026-04-20T00:00:00Z');
        $result  = $adapter->enrich($this->entry('https://dead.example/'), []);

        $this->assertTrue($result->signals['graveyard_candidate']);
        $this->assertNull($result->typeData['blog']['last_post']);
    }

    public function test_type_returns_blog(): void
    {
        $this->assertSame('blog', (new BlogAdapter(new Client(), new \DateTimeImmutable()))->type());
    }

    private function build(array $responses, string $nowIso): BlogAdapter
    {
        $mock   = new MockHandler($responses);
        $client = new Client(['handler' => HandlerStack::create($mock), 'http_errors' => false]);
        return new BlogAdapter($client, new \DateTimeImmutable($nowIso));
    }

    private function entry(string $url): Entry
    {
        return new Entry(
            name: 'test-blog',
            url: $url,
            description: null,
            type: EntryType::Blog,
            added: '2020-01-01',
        );
    }
}
