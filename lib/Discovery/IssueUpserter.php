<?php declare(strict_types=1);
namespace AwesomeList\Discovery;

use GuzzleHttp\Client;

final class IssueUpserter
{
    private const LABEL = 'discovery-candidates';

    public function __construct(
        private readonly Client $http,
        private readonly string $owner,
        private readonly string $repo,
        private readonly string $token,
    ) {}

    /** @return array<string, mixed> issue response body */
    public function upsert(string $title, string $body): array
    {
        $existing = $this->findExisting();
        if ($existing !== null) {
            $response = $this->http->patch("repos/{$this->owner}/{$this->repo}/issues/{$existing}", [
                'headers' => $this->authHeaders(),
                'json'    => ['title' => $title, 'body' => $body],
            ]);
            return json_decode((string) $response->getBody(), true, flags: JSON_THROW_ON_ERROR);
        }

        $response = $this->http->post("repos/{$this->owner}/{$this->repo}/issues", [
            'headers' => $this->authHeaders(),
            'json'    => [
                'title'  => $title,
                'body'   => $body,
                'labels' => [self::LABEL],
            ],
        ]);
        return json_decode((string) $response->getBody(), true, flags: JSON_THROW_ON_ERROR);
    }

    private function findExisting(): ?int
    {
        $response = $this->http->get("repos/{$this->owner}/{$this->repo}/issues", [
            'headers' => $this->authHeaders(),
            'query'   => ['state' => 'open', 'labels' => self::LABEL, 'per_page' => 100],
        ]);
        $issues = json_decode((string) $response->getBody(), true, flags: JSON_THROW_ON_ERROR);
        foreach ($issues as $issue) {
            if (str_contains((string) ($issue['body'] ?? ''), CandidateIssueRenderer::MARKER)) {
                return (int) $issue['number'];
            }
        }
        return null;
    }

    /** @return array<string, string> */
    private function authHeaders(): array
    {
        return [
            'Authorization' => "Bearer {$this->token}",
            'Accept'        => 'application/vnd.github+json',
            'User-Agent'    => 'awesome-magento2-discovery',
        ];
    }
}
