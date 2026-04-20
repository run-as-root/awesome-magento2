<?php declare(strict_types=1);
namespace AwesomeList\Discovery;

use DateTimeImmutable;

final class CandidateIssueRenderer
{
    public const MARKER = '<!-- candidates-issue-v1 -->';

    /**
     * @param array<int, array{repo: RepoSummary, suggested_yaml: string}> $candidates
     */
    public function render(array $candidates, CandidateLog $log, DateTimeImmutable $runAt): string
    {
        $lines = [self::MARKER, '', '# Magento 2 Discovery Candidates', ''];
        $lines[] = sprintf(
            '_Weekly scan updated %s. Check a box to auto-open a PR adding the entry to `data/`. Leave unchecked to reject (logged to `state/candidates.log.json`)._',
            $runAt->format('Y-m-d'),
        );
        $lines[] = '';
        $lines[] = sprintf('## New candidates (%d)', count($candidates));
        $lines[] = '';
        foreach ($candidates as $c) {
            $lines[] = $this->formatCandidate($c['repo'], $c['suggested_yaml']);
        }

        $history = $this->historyEntries($log);
        if ($history !== []) {
            $lines[] = '';
            $lines[] = sprintf('## Previously decided (%d)', count($history));
            $lines[] = '';
            $lines[] = '<details>';
            $lines[] = '<summary>History</summary>';
            $lines[] = '';
            foreach ($history as $h) {
                $lines[] = $h;
            }
            $lines[] = '';
            $lines[] = '</details>';
        }

        return implode("\n", $lines) . "\n";
    }

    private function formatCandidate(RepoSummary $repo, string $suggestedYaml): string
    {
        $desc = $repo->description !== null && $repo->description !== ''
            ? $repo->description
            : '_no description_';
        return sprintf(
            '- [ ] [%s](%s) ★%d — %s _(suggested: `%s`)_',
            $repo->fullName,
            $repo->htmlUrl,
            $repo->stars,
            $desc,
            $suggestedYaml,
        );
    }

    /** @return string[] */
    private function historyEntries(CandidateLog $log): array
    {
        $entries = [];
        foreach ($log->all() as $url => $row) {
            $status = $row['status'] ?? null;
            if ($status !== 'accepted' && $status !== 'rejected') {
                continue;
            }
            $decidedAt = $row['decided_at'] ?? '';
            $entries[] = [
                'url'    => $url,
                'status' => $status,
                'date'   => substr($decidedAt, 0, 10),
                'raw'    => $decidedAt,
            ];
        }
        usort($entries, fn(array $a, array $b): int => strcmp($b['raw'], $a['raw']));
        return array_map(function (array $e): string {
            $icon = $e['status'] === 'accepted' ? '✅' : '❌';
            $name = $this->nameFromUrl($e['url']);
            return sprintf('- %s [%s](%s) %s %s', $icon, $name, $e['url'], $e['status'], $e['date']);
        }, $entries);
    }

    private function nameFromUrl(string $url): string
    {
        if (preg_match('~^https?://github\.com/([^/]+/[^/]+?)(?:/|\.git|$)~', $url, $m)) {
            return $m[1];
        }
        return $url;
    }
}
