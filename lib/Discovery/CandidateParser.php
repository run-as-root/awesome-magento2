<?php declare(strict_types=1);
namespace AwesomeList\Discovery;

final class CandidateParser
{
    /** @return array<int, array{url: string, suggested_yaml: string, checked: bool}> */
    public function parse(string $body): array
    {
        // Drop anything from "## Previously decided" onwards.
        $scan = $body;
        $cutoff = strpos($body, '## Previously decided');
        if ($cutoff !== false) {
            $scan = substr($body, 0, $cutoff);
        }

        if (!preg_match_all(
            '~^- \[([x ])\] \[([^\]]+)\]\(([^)]+)\).*?_\(suggested: `([^`]+)`\)_~m',
            $scan,
            $matches,
            PREG_SET_ORDER,
        )) {
            return [];
        }

        $rows = [];
        foreach ($matches as $m) {
            $rows[] = [
                'url'            => $m[3],
                'suggested_yaml' => $m[4],
                'checked'        => $m[1] === 'x',
            ];
        }
        return $rows;
    }
}
