<?php declare(strict_types=1);
namespace AwesomeList\Tests\Enrichment;

use AwesomeList\Entry;
use AwesomeList\Enrichment\YoutubePlaylistAdapter;
use AwesomeList\EntryType;
use GuzzleHttp\Client;
use GuzzleHttp\Handler\MockHandler;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Psr7\Response;
use PHPUnit\Framework\TestCase;

final class YoutubePlaylistAdapterTest extends TestCase
{
    public function test_recent_playlist_is_actively_maintained(): void
    {
        $body = (string) file_get_contents(__DIR__ . '/../fixtures/http/youtube/playlist-items.json');
        $adapter = $this->build([new Response(200, [], $body)], '2026-04-20T00:00:00Z', 'fake-key');
        $result = $adapter->enrich($this->playlistEntry(), []);

        $this->assertTrue($result->signals['actively_maintained']);
        $this->assertSame('2026-03-01T12:00:00Z', $result->typeData['youtube']['last_upload']);
    }

    public function test_stale_channel_is_graveyard(): void
    {
        $body = (string) file_get_contents(__DIR__ . '/../fixtures/http/youtube/channel-videos.json');
        $adapter = $this->build([new Response(200, [], $body)], '2026-04-20T00:00:00Z', 'fake-key');
        $result = $adapter->enrich($this->channelEntry(), []);

        $this->assertTrue($result->signals['graveyard_candidate']);
    }

    public function test_missing_api_key_returns_empty_result(): void
    {
        $adapter = $this->build([], '2026-04-20T00:00:00Z', null);
        $result  = $adapter->enrich($this->playlistEntry(), []);

        $this->assertSame('2026-04-20T00:00:00Z', $result->lastChecked);
        $this->assertSame([], $result->signals);
    }

    public function test_type_returns_youtube_playlist(): void
    {
        $this->assertSame('youtube_playlist', (new YoutubePlaylistAdapter(new Client(), new \DateTimeImmutable(), null))->type());
    }

    private function build(array $responses, string $nowIso, ?string $apiKey): YoutubePlaylistAdapter
    {
        $mock   = new MockHandler($responses);
        $client = new Client(['handler' => HandlerStack::create($mock), 'http_errors' => false]);
        return new YoutubePlaylistAdapter($client, new \DateTimeImmutable($nowIso), $apiKey);
    }

    private function playlistEntry(): Entry
    {
        return new Entry(
            name: 'Test Playlist',
            url: 'https://www.youtube.com/playlist?list=PLxyz',
            description: null,
            type: EntryType::YoutubePlaylist,
            added: '2020-01-01',
        );
    }

    private function channelEntry(): Entry
    {
        return new Entry(
            name: 'Test Channel',
            url: 'https://www.youtube.com/channel/UCxyz',
            description: null,
            type: EntryType::YoutubePlaylist,
            added: '2020-01-01',
        );
    }
}
