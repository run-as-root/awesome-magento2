<?php declare(strict_types=1);
namespace AwesomeList\Tests\Discovery;

use AwesomeList\Discovery\CandidateFilter;
use AwesomeList\Discovery\CandidateLog;
use AwesomeList\Discovery\CategoryGuesser;
use AwesomeList\Discovery\DiscoveryScanner;
use AwesomeList\Discovery\ExistingUrlsIndex;
use AwesomeList\Discovery\GithubSearchClient;
use GuzzleHttp\Client;
use GuzzleHttp\Handler\MockHandler;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Psr7\Response;
use PHPUnit\Framework\TestCase;

final class DiscoveryScannerTest extends TestCase
{
    public function test_dedupes_across_sources_and_tags_with_category(): void
    {
        // A single repo surfaces via TWO sources (topic + org) — scan should still yield one candidate.
        $sharedRepo = $this->repoJson(
            'hyva-themes/magento2-hyva-theme',
            'https://github.com/hyva-themes/magento2-hyva-theme',
            'Hyvä theme for Magento 2.',
        );
        $paymentRepo = $this->repoJson(
            'owner/stripe-module',
            'https://github.com/owner/stripe-module',
            'Stripe payment integration.',
        );
        $topic1Body = json_encode(['items' => [$sharedRepo]]);
        $topic2Body = json_encode(['items' => [$paymentRepo]]);
        $emptyOrg   = '[]';
        $hyvaOrg    = json_encode([$sharedRepo]);

        $queue = [
            new Response(200, [], $topic1Body),   // topic:magento2
            new Response(200, [], $topic2Body),   // topic:magento-2
            new Response(200, [], $emptyOrg),     // run-as-root
            new Response(200, [], $emptyOrg),     // elgentos
            new Response(200, [], $emptyOrg),     // yireo
            new Response(200, [], $emptyOrg),     // opengento
            new Response(200, [], $emptyOrg),     // mage-os
            new Response(200, [], $hyvaOrg),      // hyva-themes
            new Response(200, [], $emptyOrg),     // magepal
        ];
        $http = new Client([
            'handler'  => HandlerStack::create(new MockHandler($queue)),
            'base_uri' => 'https://api.github.com/',
        ]);

        $scanner = new DiscoveryScanner(
            new GithubSearchClient($http),
            new CandidateFilter(new \DateTimeImmutable('2026-04-20T00:00:00Z')),
            new CategoryGuesser(),
        );

        $emptyIndex = ExistingUrlsIndex::build(__DIR__ . '/../fixtures/enrichment/data/does-not-exist');
        $emptyLog   = CandidateLog::loadOrEmpty(__DIR__ . '/../fixtures/state/does-not-exist.json');

        $results = $scanner->scan($emptyIndex, $emptyLog);

        $this->assertCount(2, $results, 'dedupe should collapse the shared repo across sources');

        $urls = array_map(fn(array $r): string => $r['repo']->htmlUrl, $results);
        $this->assertContains('https://github.com/hyva-themes/magento2-hyva-theme', $urls);
        $this->assertContains('https://github.com/owner/stripe-module', $urls);

        $categories = [];
        foreach ($results as $r) {
            $categories[$r['repo']->htmlUrl] = $r['suggested_yaml'];
        }
        $this->assertSame('extensions/pwa.yml',     $categories['https://github.com/hyva-themes/magento2-hyva-theme']);
        $this->assertSame('extensions/payment.yml', $categories['https://github.com/owner/stripe-module']);
    }

    /** @return array<string, mixed> */
    private function repoJson(string $fullName, string $htmlUrl, string $description): array
    {
        return [
            'full_name'        => $fullName,
            'html_url'         => $htmlUrl,
            'description'      => $description,
            'stargazers_count' => 200,
            'pushed_at'        => '2026-04-01T00:00:00Z',
            'created_at'       => '2025-01-01T00:00:00Z',  // 15 months old, velocity ~13/mo — passes filter
            'archived'         => false,
            'fork'             => false,
            'license'          => ['spdx_id' => 'MIT'],
            'default_branch'   => 'main',
        ];
    }
}
