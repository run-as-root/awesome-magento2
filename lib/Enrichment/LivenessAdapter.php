<?php declare(strict_types=1);
namespace AwesomeList\Enrichment;

use AwesomeList\Entry;
use DateTimeImmutable;
use GuzzleHttp\Client;
use GuzzleHttp\Exception\TransferException;

final class LivenessAdapter implements EnrichmentAdapter
{
    private const GRAVEYARD_DAYS = 90;

    public function __construct(
        private readonly Client $http,
        private readonly DateTimeImmutable $now,
        private readonly string $type,
    ) {}

    public function type(): string
    {
        return $this->type;
    }

    public function enrich(Entry $entry, array $priorState): EnrichmentResult
    {
        $status      = $this->checkUrl($entry->url ?? '');
        $priorStatus = $priorState['signals']['link_status'] ?? null;
        $priorSince  = $priorState['liveness']['link_status_since'] ?? null;
        $since = ($priorStatus === $status && $priorSince !== null)
            ? $priorSince
            : $this->now->format('Y-m-d\TH:i:s\Z');

        $graveyard = $status === 'broken'
            && $this->daysSince($since) > self::GRAVEYARD_DAYS;

        return new EnrichmentResult(
            lastChecked: $this->now->format('Y-m-d\TH:i:s\Z'),
            signals: [
                'link_status'         => $status,
                'graveyard_candidate' => $graveyard,
            ],
            typeData: ['liveness' => ['link_status_since' => $since]],
        );
    }

    private function checkUrl(string $url): string
    {
        try {
            $response = $this->http->get($url, [
                'timeout'         => 10,
                'allow_redirects' => true,
                'http_errors'     => false,
            ]);
            $code = $response->getStatusCode();
            return $code >= 200 && $code < 400 ? 'ok' : 'broken';
        } catch (TransferException) {
            return 'broken';
        }
    }

    private function daysSince(string $iso): int
    {
        $then = new DateTimeImmutable($iso);
        $diff = $this->now->diff($then);
        return $diff->invert === 1 ? (int) $diff->days : 0;
    }
}
