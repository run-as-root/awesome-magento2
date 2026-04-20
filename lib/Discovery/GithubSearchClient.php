<?php declare(strict_types=1);
namespace AwesomeList\Discovery;

use GuzzleHttp\Client;

final class GithubSearchClient
{
    public function __construct(private readonly Client $http) {}

    /** @return RepoSummary[] */
    public function topicSearch(string $topic, int $minStars = 10): array
    {
        $response = $this->http->get('search/repositories', [
            'query' => ['q' => "topic:$topic stars:>=$minStars", 'per_page' => 50, 'sort' => 'updated'],
        ]);
        $body = json_decode((string) $response->getBody(), true, flags: JSON_THROW_ON_ERROR);
        return array_map([RepoSummary::class, 'fromArray'], $body['items'] ?? []);
    }

    /** @return RepoSummary[] */
    public function orgRepos(string $org): array
    {
        $response = $this->http->get("orgs/$org/repos", ['query' => ['per_page' => 100, 'type' => 'sources']]);
        $body = json_decode((string) $response->getBody(), true, flags: JSON_THROW_ON_ERROR);
        return array_map([RepoSummary::class, 'fromArray'], $body ?? []);
    }
}
