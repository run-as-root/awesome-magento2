<?php declare(strict_types=1);
namespace AwesomeList\Enrichment;

use AwesomeList\Entry;
use DateTimeImmutable;
use GuzzleHttp\Client;
use GuzzleHttp\Exception\TransferException;

final class PodcastAdapter implements EnrichmentAdapter
{
    private const ACTIVE_EPISODE_DAYS    = 365;
    private const GRAVEYARD_EPISODE_DAYS = 730;
    private const FALLBACK_FEED_PATHS    = ['/feed', '/feed/', '/rss.xml', '/atom.xml'];

    public function __construct(
        private readonly Client $http,
        private readonly DateTimeImmutable $now,
    ) {}

    public function type(): string
    {
        return 'podcast';
    }

    public function enrich(Entry $entry, array $priorState): EnrichmentResult
    {
        $url         = $entry->url ?? '';
        $feedUrl     = $this->discoverFeed($url);
        $lastEpisode = $feedUrl !== null ? $this->latestEpisodeFrom($feedUrl) : null;

        $active = $lastEpisode !== null
            && $this->daysSince($lastEpisode) <= self::ACTIVE_EPISODE_DAYS;

        $graveyard = $lastEpisode === null
            || $this->daysSince($lastEpisode) > self::GRAVEYARD_EPISODE_DAYS;

        return new EnrichmentResult(
            lastChecked: $this->now->format('Y-m-d\TH:i:s\Z'),
            signals: [
                'actively_maintained' => $active,
                'graveyard_candidate' => $graveyard,
                'vitality_hot'        => false,
            ],
            typeData: ['podcast' => [
                'feed_url'     => $feedUrl,
                'last_episode' => $lastEpisode,
            ]],
        );
    }

    private function discoverFeed(string $url): ?string
    {
        $html = $this->fetchBody($url);
        if ($html === null) {
            return null;
        }
        if (preg_match(
            '~<link[^>]+rel=["\']alternate["\'][^>]+type=["\']application/(rss|atom)\+xml["\'][^>]+href=["\']([^"\']+)["\']~i',
            $html,
            $m,
        )) {
            return $this->resolveUrl($url, $m[2]);
        }
        foreach (self::FALLBACK_FEED_PATHS as $path) {
            $candidate = $this->resolveUrl($url, $path);
            if ($this->headOk($candidate)) {
                return $candidate;
            }
        }
        return null;
    }

    private function latestEpisodeFrom(string $feedUrl): ?string
    {
        $body = $this->fetchBody($feedUrl);
        if ($body === null) {
            return null;
        }
        $doc = @simplexml_load_string($body);
        if ($doc === false) {
            return null;
        }
        $dates = [];
        foreach ($doc->channel->item ?? [] as $item) {
            if (!empty((string) $item->pubDate)) {
                $dates[] = (string) $item->pubDate;
            }
        }
        foreach ($doc->entry ?? [] as $entry) {
            if (!empty((string) $entry->updated)) {
                $dates[] = (string) $entry->updated;
            }
        }
        if ($dates === []) {
            return null;
        }
        $timestamps = array_filter(array_map('strtotime', $dates));
        if ($timestamps === []) {
            return null;
        }
        return gmdate('Y-m-d\TH:i:s\Z', max($timestamps));
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

    private function headOk(string $url): bool
    {
        try {
            $response = $this->http->get($url, ['timeout' => 10]);
            return $response->getStatusCode() < 400;
        } catch (TransferException) {
            return false;
        }
    }

    private function resolveUrl(string $base, string $relative): string
    {
        if (preg_match('~^https?://~i', $relative)) {
            return $relative;
        }
        $baseHost = rtrim(
            parse_url($base, PHP_URL_SCHEME) . '://' . parse_url($base, PHP_URL_HOST),
            '/',
        );
        return $baseHost . '/' . ltrim($relative, '/');
    }

    private function daysSince(string $iso): int
    {
        $then = new DateTimeImmutable($iso);
        $diff = $this->now->diff($then);
        return $diff->invert === 1 ? (int) $diff->days : 0;
    }
}
