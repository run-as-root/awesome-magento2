<?php declare(strict_types=1);
namespace AwesomeList\Tests\Enrichment;

use AwesomeList\Enrichment\AdapterFactory;
use AwesomeList\Enrichment\Enricher;
use AwesomeList\Enrichment\GithubRepoAdapter;
use AwesomeList\Enrichment\VitalityRanker;
use AwesomeList\YamlEntryLoader;
use GuzzleHttp\Client;
use GuzzleHttp\Handler\MockHandler;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Psr7\Response;
use PHPUnit\Framework\TestCase;

final class EnricherTest extends TestCase
{
    public function test_it_enriches_only_supported_types_and_keys_by_url(): void
    {
        $repo    = (string) file_get_contents(__DIR__ . '/../fixtures/http/github/repos-active.json');
        $release = (string) file_get_contents(__DIR__ . '/../fixtures/http/github/releases-active.json');
        $mock    = new MockHandler([new Response(200, [], $repo), new Response(200, [], $release)]);
        $client  = new Client(['handler' => HandlerStack::create($mock), 'base_uri' => 'https://api.github.com/']);
        $adapter = new GithubRepoAdapter($client, new \DateTimeImmutable('2026-04-19T02:00:00Z'));

        $enricher = new Enricher(
            new YamlEntryLoader(),
            new AdapterFactory([$adapter]),
            new VitalityRanker(),
        );

        $state = $enricher->enrichDirectory(
            __DIR__ . '/../fixtures/enrichment/data',
            __DIR__ . '/../fixtures/state/empty.json',
        );

        $this->assertArrayHasKey('https://github.com/netz98/n98-magerun2', $state);
        $this->assertArrayNotHasKey('https://hyva.io/', $state); // vendor_site has no Phase 2 adapter
        $this->assertSame(2147, $state['https://github.com/netz98/n98-magerun2']['github']['stars']);
        $this->assertTrue($state['https://github.com/netz98/n98-magerun2']['signals']['actively_maintained']);
    }

    public function test_it_passes_prior_state_to_adapter(): void
    {
        $seen = null;
        $spy = new class ($seen) implements \AwesomeList\Enrichment\EnrichmentAdapter {
            public function __construct(private ?array &$seen) {}
            public function type(): string { return 'github_repo'; }
            public function enrich(\AwesomeList\Entry $entry, array $priorState): \AwesomeList\Enrichment\EnrichmentResult
            {
                $this->seen = $priorState;
                return new \AwesomeList\Enrichment\EnrichmentResult('2026-04-20T00:00:00Z', []);
            }
        };

        $enricher = new Enricher(
            new YamlEntryLoader(),
            new AdapterFactory([$spy]),
            new VitalityRanker(),
        );
        $enricher->enrichDirectory(
            __DIR__ . '/../fixtures/enrichment/data',
            __DIR__ . '/../fixtures/state/enrichment.sample.json',
        );

        $this->assertNotNull($seen);
        $this->assertTrue($seen['signals']['vitality_hot']);
    }
}
