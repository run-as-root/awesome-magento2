<?php declare(strict_types=1);
namespace AwesomeList\Enrichment;

use AwesomeList\Entry;
use DateTimeImmutable;
use GuzzleHttp\Client;
use GuzzleHttp\Exception\ClientException;
use RuntimeException;

final class PackagistAdapter implements EnrichmentAdapter
{
    private const ACTIVE_RELEASE_DAYS = 180;

    public function __construct(
        private readonly Client $http,
        private readonly DateTimeImmutable $now,
    ) {}

    public function type(): string
    {
        return 'packagist_pkg';
    }

    public function enrich(Entry $entry, array $priorState): EnrichmentResult
    {
        [$vendor, $name] = $this->parseUrl($entry->url ?? '');
        $data = $this->fetch("https://packagist.org/packages/$vendor/$name.json");

        if ($data === null) {
            return new EnrichmentResult(
                lastChecked: $this->now->format('Y-m-d\TH:i:s\Z'),
                signals: [
                    'actively_maintained' => false,
                    'graveyard_candidate' => true,
                    'vitality_hot'        => false,
                ],
                typeData: ['packagist' => [
                    'downloads_total'   => 0,
                    'downloads_monthly' => 0,
                    'last_release'      => null,
                    'abandoned'         => false,
                ]],
            );
        }

        $pkg         = $data['package'] ?? [];
        $abandoned   = !empty($pkg['abandoned']);
        $downloads   = $pkg['downloads'] ?? [];
        $lastRelease = $this->newestReleaseDate($pkg['versions'] ?? []);
        $activelyMaintained = !$abandoned
            && $lastRelease !== null
            && $this->daysSince($lastRelease) <= self::ACTIVE_RELEASE_DAYS;

        return new EnrichmentResult(
            lastChecked: $this->now->format('Y-m-d\TH:i:s\Z'),
            signals: [
                'actively_maintained' => $activelyMaintained,
                'graveyard_candidate' => $abandoned,
                'vitality_hot'        => false,
            ],
            typeData: ['packagist' => [
                'downloads_total'   => (int) ($downloads['total'] ?? 0),
                'downloads_monthly' => (int) ($downloads['monthly'] ?? 0),
                'last_release'      => $lastRelease,
                'abandoned'         => (bool) $abandoned,
            ]],
        );
    }

    private function parseUrl(string $url): array
    {
        $path = parse_url($url, PHP_URL_PATH) ?? '';
        if (!preg_match('~^/packages/([^/]+)/([^/]+)/?$~', $path, $m)) {
            throw new RuntimeException("PackagistAdapter cannot parse url: $url");
        }
        return [$m[1], $m[2]];
    }

    private function fetch(string $url): ?array
    {
        try {
            $response = $this->http->get($url, ['timeout' => 15]);
        } catch (ClientException $e) {
            if ($e->getCode() === 404) {
                return null;
            }
            throw $e;
        }
        $status = $response->getStatusCode();
        if ($status === 404) {
            return null;
        }
        if ($status >= 400) {
            throw new RuntimeException("Packagist returned $status for $url");
        }
        return json_decode((string) $response->getBody(), true, flags: JSON_THROW_ON_ERROR);
    }

    private function newestReleaseDate(array $versions): ?string
    {
        $times = [];
        foreach ($versions as $v) {
            if (!empty($v['time'])) {
                $times[] = $v['time'];
            }
        }
        if ($times === []) {
            return null;
        }
        rsort($times);
        return $times[0];
    }

    private function daysSince(string $iso): int
    {
        $then = new DateTimeImmutable($iso);
        $diff = $this->now->diff($then);
        return $diff->invert === 1 ? (int) $diff->days : 0;
    }
}
