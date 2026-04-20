<?php declare(strict_types=1);
namespace AwesomeList\Enrichment;

use AwesomeList\Entry;
use DateTimeImmutable;
use GuzzleHttp\Client;
use GuzzleHttp\Exception\TransferException;

final class YoutubePlaylistAdapter implements EnrichmentAdapter
{
    private const ACTIVE_UPLOAD_DAYS    = 90;
    private const GRAVEYARD_UPLOAD_DAYS = 540;

    public function __construct(
        private readonly Client $http,
        private readonly DateTimeImmutable $now,
        private readonly ?string $apiKey,
    ) {}

    public function type(): string
    {
        return 'youtube_playlist';
    }

    public function enrich(Entry $entry, array $priorState): EnrichmentResult
    {
        if ($this->apiKey === null) {
            return new EnrichmentResult(
                lastChecked: $this->now->format('Y-m-d\TH:i:s\Z'),
                signals: [],
            );
        }

        $url = $entry->url ?? '';
        $endpoint = $this->endpointFor($url);
        if ($endpoint === null) {
            return new EnrichmentResult(
                lastChecked: $this->now->format('Y-m-d\TH:i:s\Z'),
                signals: [
                    'actively_maintained' => false,
                    'graveyard_candidate' => true,
                    'vitality_hot'        => false,
                ],
                typeData: ['youtube' => ['last_upload' => null]],
            );
        }

        $items = $this->fetchItems($endpoint);
        $lastUpload = $this->newestItemDate($items);

        $active = $lastUpload !== null
            && $this->daysSince($lastUpload) <= self::ACTIVE_UPLOAD_DAYS;
        $graveyard = $lastUpload === null
            || $this->daysSince($lastUpload) > self::GRAVEYARD_UPLOAD_DAYS;

        return new EnrichmentResult(
            lastChecked: $this->now->format('Y-m-d\TH:i:s\Z'),
            signals: [
                'actively_maintained' => $active,
                'graveyard_candidate' => $graveyard,
                'vitality_hot'        => false,
            ],
            typeData: ['youtube' => ['last_upload' => $lastUpload]],
        );
    }

    private function endpointFor(string $url): ?string
    {
        if (preg_match('~[?&]list=([A-Za-z0-9_-]+)~', $url, $m)) {
            return sprintf(
                'https://www.googleapis.com/youtube/v3/playlistItems?part=snippet&maxResults=5&playlistId=%s&key=%s',
                $m[1], $this->apiKey,
            );
        }
        if (preg_match('~/channel/(UC[A-Za-z0-9_-]+)~', $url, $m)) {
            return sprintf(
                'https://www.googleapis.com/youtube/v3/search?part=snippet&order=date&type=video&maxResults=5&channelId=%s&key=%s',
                $m[1], $this->apiKey,
            );
        }
        return null;
    }

    private function fetchItems(string $endpoint): array
    {
        try {
            $response = $this->http->get($endpoint, ['timeout' => 15]);
            if ($response->getStatusCode() >= 400) {
                return [];
            }
            $body = json_decode((string) $response->getBody(), true, flags: JSON_THROW_ON_ERROR);
            return $body['items'] ?? [];
        } catch (TransferException) {
            return [];
        }
    }

    private function newestItemDate(array $items): ?string
    {
        $dates = [];
        foreach ($items as $item) {
            $published = $item['snippet']['publishedAt'] ?? null;
            if ($published !== null) {
                $dates[] = $published;
            }
        }
        if ($dates === []) {
            return null;
        }
        rsort($dates);
        return $dates[0];
    }

    private function daysSince(string $iso): int
    {
        $then = new DateTimeImmutable($iso);
        $diff = $this->now->diff($then);
        return $diff->invert === 1 ? (int) $diff->days : 0;
    }
}
