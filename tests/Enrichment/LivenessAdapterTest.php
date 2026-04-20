<?php declare(strict_types=1);
namespace AwesomeList\Tests\Enrichment;

use AwesomeList\Entry;
use AwesomeList\Enrichment\LivenessAdapter;
use AwesomeList\EntryType;
use GuzzleHttp\Client;
use GuzzleHttp\Handler\MockHandler;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Psr7\Response;
use PHPUnit\Framework\TestCase;

final class LivenessAdapterTest extends TestCase
{
    public function test_200_means_ok_with_no_graveyard(): void
    {
        $adapter = $this->build(new Response(200), new \DateTimeImmutable('2026-04-20T00:00:00Z'));
        $result  = $adapter->enrich($this->entry(), []);

        $this->assertSame('ok', $result->signals['link_status']);
        $this->assertFalse($result->signals['graveyard_candidate']);
        $this->assertSame('2026-04-20T00:00:00Z', $result->typeData['liveness']['link_status_since']);
    }

    public function test_404_sets_broken_and_carries_link_status_since_forward(): void
    {
        $now = new \DateTimeImmutable('2026-04-20T00:00:00Z');
        $prior = [
            'liveness' => [
                'link_status_since' => '2026-03-01T00:00:00Z',
            ],
            'signals' => ['link_status' => 'broken'],
        ];
        $adapter = $this->build(new Response(404), $now);
        $result  = $adapter->enrich($this->entry(), $prior);

        $this->assertSame('broken', $result->signals['link_status']);
        $this->assertSame('2026-03-01T00:00:00Z', $result->typeData['liveness']['link_status_since']);
        $this->assertFalse($result->signals['graveyard_candidate']); // 50 days broken < 90
    }

    public function test_broken_more_than_90_days_triggers_graveyard(): void
    {
        $now = new \DateTimeImmutable('2026-04-20T00:00:00Z');
        $prior = [
            'liveness' => [
                'link_status_since' => '2026-01-10T00:00:00Z', // 100 days earlier
            ],
            'signals' => ['link_status' => 'broken'],
        ];
        $adapter = $this->build(new Response(404), $now);
        $result  = $adapter->enrich($this->entry(), $prior);

        $this->assertTrue($result->signals['graveyard_candidate']);
    }

    public function test_transition_from_broken_to_ok_resets_link_status_since(): void
    {
        $now = new \DateTimeImmutable('2026-04-20T00:00:00Z');
        $prior = [
            'liveness' => ['link_status_since' => '2025-01-01T00:00:00Z'],
            'signals'  => ['link_status' => 'broken'],
        ];
        $adapter = $this->build(new Response(200), $now);
        $result  = $adapter->enrich($this->entry(), $prior);

        $this->assertSame('ok', $result->signals['link_status']);
        $this->assertSame('2026-04-20T00:00:00Z', $result->typeData['liveness']['link_status_since']);
        $this->assertFalse($result->signals['graveyard_candidate']);
    }

    public function test_type_is_configurable(): void
    {
        $this->assertSame('vendor_site', (new LivenessAdapter(new Client(), new \DateTimeImmutable(), 'vendor_site'))->type());
        $this->assertSame('course',      (new LivenessAdapter(new Client(), new \DateTimeImmutable(), 'course'))->type());
        $this->assertSame('canonical',   (new LivenessAdapter(new Client(), new \DateTimeImmutable(), 'canonical'))->type());
    }

    private function build(Response $response, \DateTimeImmutable $now): LivenessAdapter
    {
        $mock   = new MockHandler([$response]);
        $client = new Client(['handler' => HandlerStack::create($mock)]);
        return new LivenessAdapter($client, $now, 'vendor_site');
    }

    private function entry(): Entry
    {
        return new Entry(
            name: 'Example',
            url: 'https://example.com',
            description: null,
            type: EntryType::VendorSite,
            added: '2020-01-01',
        );
    }
}
