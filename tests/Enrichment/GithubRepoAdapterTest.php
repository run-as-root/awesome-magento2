<?php declare(strict_types=1);
namespace AwesomeList\Tests\Enrichment;

use AwesomeList\Entry;
use AwesomeList\Enrichment\GithubRepoAdapter;
use AwesomeList\EntryType;
use GuzzleHttp\Client;
use GuzzleHttp\Handler\MockHandler;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Psr7\Response;
use PHPUnit\Framework\TestCase;

final class GithubRepoAdapterTest extends TestCase
{
    public function test_it_reports_active_maintenance_for_recent_repo(): void
    {
        $now     = new \DateTimeImmutable('2026-04-19T02:00:00Z');
        $adapter = $this->buildAdapter([
            new Response(200, [], (string) file_get_contents(__DIR__ . '/../fixtures/http/github/repos-active.json')),
            new Response(200, [], (string) file_get_contents(__DIR__ . '/../fixtures/http/github/releases-active.json')),
        ], $now);

        $result = $adapter->enrich($this->entry('https://github.com/netz98/n98-magerun2'));

        $this->assertSame('2026-04-19T02:00:00Z', $result->lastChecked);
        $this->assertTrue($result->signals['actively_maintained']);
        $this->assertFalse($result->signals['graveyard_candidate']);
        $this->assertSame(2147, $result->typeData['github']['stars']);
        $this->assertSame('2026-04-15T09:23:00Z', $result->typeData['github']['last_commit']);
        $this->assertSame('2026-03-28T00:00:00Z', $result->typeData['github']['last_release']);
    }

    public function test_archived_repo_is_a_graveyard_candidate(): void
    {
        $now     = new \DateTimeImmutable('2026-04-19T02:00:00Z');
        $adapter = $this->buildAdapter([
            new Response(200, [], (string) file_get_contents(__DIR__ . '/../fixtures/http/github/repos-archived.json')),
            new Response(404),
        ], $now);

        $result = $adapter->enrich($this->entry('https://github.com/someone/abandoned-thing'));

        $this->assertTrue($result->signals['graveyard_candidate']);
        $this->assertFalse($result->signals['actively_maintained']);
        $this->assertTrue($result->typeData['github']['archived']);
        $this->assertNull($result->typeData['github']['last_release']);
    }

    public function test_stale_repo_without_release_is_graveyard_but_not_archived(): void
    {
        $now     = new \DateTimeImmutable('2026-04-19T02:00:00Z');
        $adapter = $this->buildAdapter([
            new Response(200, [], (string) file_get_contents(__DIR__ . '/../fixtures/http/github/repos-stale.json')),
            new Response(404),
        ], $now);

        $result = $adapter->enrich($this->entry('https://github.com/org/stale-repo'));

        $this->assertTrue($result->signals['graveyard_candidate']);
        $this->assertFalse($result->signals['actively_maintained']);
        $this->assertFalse($result->typeData['github']['archived']);
    }

    public function test_type_returns_github_repo(): void
    {
        $this->assertSame('github_repo', (new GithubRepoAdapter(new Client(), new \DateTimeImmutable()))->type());
    }

    private function buildAdapter(array $queuedResponses, \DateTimeImmutable $now): GithubRepoAdapter
    {
        $mock   = new MockHandler($queuedResponses);
        $client = new Client(['handler' => HandlerStack::create($mock), 'base_uri' => 'https://api.github.com/']);
        return new GithubRepoAdapter($client, $now);
    }

    private function entry(string $url): Entry
    {
        return new Entry(
            name: 'test',
            url: $url,
            description: null,
            type: EntryType::GithubRepo,
            added: '2020-01-01',
        );
    }
}
