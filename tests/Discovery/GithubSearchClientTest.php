<?php declare(strict_types=1);
namespace AwesomeList\Tests\Discovery;

use AwesomeList\Discovery\GithubSearchClient;
use GuzzleHttp\Client;
use GuzzleHttp\Handler\MockHandler;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Psr7\Response;
use PHPUnit\Framework\TestCase;

final class GithubSearchClientTest extends TestCase
{
    public function test_topic_search_returns_repo_summaries(): void
    {
        $body = (string) file_get_contents(__DIR__ . '/../fixtures/http/discovery/search-topic-magento2.json');
        $mock = new MockHandler([new Response(200, [], $body)]);
        $http = new Client(['handler' => HandlerStack::create($mock), 'base_uri' => 'https://api.github.com/']);

        $client = new GithubSearchClient($http);
        $results = $client->topicSearch('magento2');

        $this->assertCount(2, $results);
        $this->assertSame('alpha/beta', $results[0]->fullName);
        $this->assertSame(42, $results[0]->stars);
        $this->assertTrue($results[1]->archived);
    }

    public function test_org_repos_returns_repo_summaries(): void
    {
        $body = (string) file_get_contents(__DIR__ . '/../fixtures/http/discovery/org-repos.json');
        $mock = new MockHandler([new Response(200, [], $body)]);
        $http = new Client(['handler' => HandlerStack::create($mock), 'base_uri' => 'https://api.github.com/']);

        $client = new GithubSearchClient($http);
        $results = $client->orgRepos('run-as-root');

        $this->assertCount(1, $results);
        $this->assertSame('run-as-root/magento2-prometheus-exporter', $results[0]->fullName);
    }
}
