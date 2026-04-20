<?php declare(strict_types=1);
namespace AwesomeList\Discovery;

final class DiscoveryScanner
{
    private const KNOWN_ORGS = [
        'run-as-root', 'elgentos', 'yireo', 'opengento', 'mage-os', 'hyva-themes', 'magepal',
    ];

    public function __construct(
        private readonly GithubSearchClient $search,
        private readonly CandidateFilter $filter,
        private readonly CategoryGuesser $guesser,
    ) {}

    /** @return array<int, array{repo: RepoSummary, suggested_yaml: string}> */
    public function scan(ExistingUrlsIndex $index, CandidateLog $log): array
    {
        $byUrl = [];
        foreach (['magento2', 'magento-2'] as $topic) {
            foreach ($this->search->topicSearch($topic) as $repo) {
                $byUrl[$repo->htmlUrl] = $repo;
            }
        }
        foreach (self::KNOWN_ORGS as $org) {
            foreach ($this->search->orgRepos($org) as $repo) {
                $byUrl[$repo->htmlUrl] = $repo;
            }
        }
        $filtered = $this->filter->filter(array_values($byUrl), $index, $log);
        $out = [];
        foreach ($filtered as $repo) {
            $out[] = ['repo' => $repo, 'suggested_yaml' => $this->guesser->guess($repo)];
        }
        return $out;
    }
}
