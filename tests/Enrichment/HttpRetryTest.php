<?php declare(strict_types=1);
namespace AwesomeList\Tests\Enrichment;

use AwesomeList\Enrichment\HttpRetry;
use GuzzleHttp\Client;
use GuzzleHttp\Exception\ConnectException;
use GuzzleHttp\Handler\MockHandler;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Psr7\Request;
use GuzzleHttp\Psr7\Response;
use PHPUnit\Framework\TestCase;

final class HttpRetryTest extends TestCase
{
    public function test_retries_on_429_until_success(): void
    {
        $mock = new MockHandler([
            new Response(429, ['Retry-After' => '0']),
            new Response(429, ['Retry-After' => '0']),
            new Response(200, [], '{"ok":true}'),
        ]);
        $stack = HandlerStack::create($mock);
        $stack->push(HttpRetry::middleware());
        $http = new Client(['handler' => $stack, 'http_errors' => false]);

        $response = $http->get('https://example.com');

        $this->assertSame(200, $response->getStatusCode());
        $this->assertSame(0, $mock->count()); // queue exhausted = all 3 responses consumed
    }

    public function test_retries_on_5xx_then_gives_up_after_max_attempts(): void
    {
        $mock = new MockHandler([
            new Response(500),
            new Response(503),
            new Response(502),
            new Response(500), // final attempt after 3 retries
        ]);
        $stack = HandlerStack::create($mock);
        $stack->push(HttpRetry::middleware());
        $http = new Client(['handler' => $stack, 'http_errors' => false]);

        $response = $http->get('https://example.com');

        $this->assertSame(500, $response->getStatusCode()); // last attempt surfaces unchanged
    }

    public function test_does_not_retry_on_404(): void
    {
        $mock = new MockHandler([new Response(404)]);
        $stack = HandlerStack::create($mock);
        $stack->push(HttpRetry::middleware());
        $http = new Client(['handler' => $stack, 'http_errors' => false]);

        $response = $http->get('https://example.com');

        $this->assertSame(404, $response->getStatusCode());
        $this->assertSame(0, $mock->count());
    }

    public function test_retries_on_github_rate_limit_403(): void
    {
        $mock = new MockHandler([
            new Response(403, ['X-RateLimit-Remaining' => '0', 'X-RateLimit-Reset' => (string) time()]),
            new Response(200, [], '{}'),
        ]);
        $stack = HandlerStack::create($mock);
        $stack->push(HttpRetry::middleware());
        $http = new Client(['handler' => $stack, 'http_errors' => false]);

        $response = $http->get('https://example.com');

        $this->assertSame(200, $response->getStatusCode());
    }

    public function test_connection_error_retries_then_succeeds(): void
    {
        $mock = new MockHandler([
            new ConnectException('DNS unresolvable', new Request('GET', 'https://example.com')),
            new Response(200, [], '{}'),
        ]);
        $stack = HandlerStack::create($mock);
        $stack->push(HttpRetry::middleware());
        $http = new Client(['handler' => $stack, 'http_errors' => false]);

        $response = $http->get('https://example.com');

        $this->assertSame(200, $response->getStatusCode());
    }
}
