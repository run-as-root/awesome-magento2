<?php declare(strict_types=1);
namespace AwesomeList\Enrichment;

use AwesomeList\Entry;
use DateTimeImmutable;
use GuzzleHttp\Client;
use GuzzleHttp\Exception\TransferException;

final class EventAdapter implements EnrichmentAdapter
{
    public function __construct(
        private readonly Client $http,
        private readonly DateTimeImmutable $now,
    ) {}

    public function type(): string
    {
        return 'event';
    }

    public function enrich(Entry $entry, array $priorState): EnrichmentResult
    {
        $body = $this->fetchBody($entry->url ?? '');
        if ($body === null) {
            return new EnrichmentResult(
                lastChecked: $this->now->format('Y-m-d\TH:i:s\Z'),
                signals: [
                    'actively_maintained' => false,
                    'graveyard_candidate' => true,
                    'vitality_hot'        => false,
                ],
                typeData: ['event' => ['latest_year_on_page' => null]],
            );
        }

        $latestYear = $this->scanYears($body);
        $currentYear = (int) $this->now->format('Y');
        $active = $latestYear !== null && $latestYear >= $currentYear;

        return new EnrichmentResult(
            lastChecked: $this->now->format('Y-m-d\TH:i:s\Z'),
            signals: [
                'actively_maintained' => $active,
                'graveyard_candidate' => false,
                'vitality_hot'        => false,
            ],
            typeData: ['event' => ['latest_year_on_page' => $latestYear]],
        );
    }

    private function scanYears(string $body): ?int
    {
        if (!preg_match_all('/(?<!\d)(20\d{2})(?!\d)/', $body, $m)) {
            return null;
        }
        $currentYear = (int) $this->now->format('Y');
        $years = array_map('intval', $m[1]);
        $years = array_filter($years, fn(int $y): bool => $y <= $currentYear + 1);
        if ($years === []) {
            return null;
        }
        return max($years);
    }

    private function fetchBody(string $url): ?string
    {
        try {
            $response = $this->http->get($url, ['timeout' => 15]);
            if ($response->getStatusCode() >= 400) {
                return null;
            }
            return (string) $response->getBody();
        } catch (TransferException) {
            return null;
        }
    }
}
