<?php declare(strict_types=1);
namespace AwesomeList\Tests\Discovery;

use AwesomeList\Discovery\CandidateIssueRenderer;
use AwesomeList\Discovery\IssueUpserter;
use GuzzleHttp\Client;
use GuzzleHttp\Handler\MockHandler;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Middleware;
use ArrayObject;
use GuzzleHttp\Psr7\Request;
use GuzzleHttp\Psr7\Response;
use PHPUnit\Framework\TestCase;

final class IssueUpserterTest extends TestCase
{
    public function test_patches_existing_issue_when_marker_found(): void
    {
        $existingList = json_encode([
            [
                'number' => 17,
                'title'  => 'Random',
                'body'   => 'Something else entirely.',
            ],
            [
                'number' => 42,
                'title'  => 'Magento 2 Discovery Candidates',
                'body'   => CandidateIssueRenderer::MARKER . "\nold body",
            ],
        ]);
        $mock = new MockHandler([
            new Response(200, [], $existingList),
            new Response(200, [], '{"number":42}'),
        ]);
        [$http, $log] = $this->clientWithHistory($mock);
        $upserter = new IssueUpserter($http, 'run-as-root', 'awesome-magento2', 'fake-token');

        $upserter->upsert('Magento 2 Discovery Candidates', "new body with " . CandidateIssueRenderer::MARKER);

        $this->assertCount(2, $log);
        $this->assertSame('GET',   $log[0]['request']->getMethod());
        $this->assertStringContainsString('/repos/run-as-root/awesome-magento2/issues', (string) $log[0]['request']->getUri());
        $this->assertSame('PATCH', $log[1]['request']->getMethod());
        $this->assertStringContainsString('/issues/42', (string) $log[1]['request']->getUri());
    }

    public function test_posts_new_issue_when_marker_not_found(): void
    {
        $existingList = json_encode([
            ['number' => 17, 'title' => 'Random', 'body' => 'No marker.'],
        ]);
        $mock = new MockHandler([
            new Response(200, [], $existingList),
            new Response(201, [], '{"number":99}'),
        ]);
        [$http, $log] = $this->clientWithHistory($mock);
        $upserter = new IssueUpserter($http, 'run-as-root', 'awesome-magento2', 'fake-token');

        $upserter->upsert('Magento 2 Discovery Candidates', "new body with " . CandidateIssueRenderer::MARKER);

        $this->assertCount(2, $log);
        $this->assertSame('POST', $log[1]['request']->getMethod());
        $this->assertStringContainsString('/repos/run-as-root/awesome-magento2/issues', (string) $log[1]['request']->getUri());
        $createBody = json_decode((string) $log[1]['request']->getBody(), true);
        $this->assertSame(['discovery-candidates'], $createBody['labels']);
        $this->assertSame('Magento 2 Discovery Candidates', $createBody['title']);
    }

    /** @return array{0: Client, 1: ArrayObject<int, array{request: Request}>} */
    private function clientWithHistory(MockHandler $mock): array
    {
        $history = new ArrayObject();
        $stack = HandlerStack::create($mock);
        $stack->push(Middleware::history($history));
        $http = new Client(['handler' => $stack, 'base_uri' => 'https://api.github.com/']);
        return [$http, $history];
    }
}
