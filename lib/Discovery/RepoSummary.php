<?php declare(strict_types=1);
namespace AwesomeList\Discovery;

final class RepoSummary
{
    public function __construct(
        public readonly string $fullName,
        public readonly string $htmlUrl,
        public readonly ?string $description,
        public readonly int $stars,
        public readonly ?string $pushedAt,
        public readonly ?string $createdAt,
        public readonly bool $archived,
        public readonly bool $fork,
        public readonly ?string $licenseSpdx,
        public readonly ?string $defaultBranch,
    ) {}

    public static function fromArray(array $row): self
    {
        return new self(
            fullName:      (string) ($row['full_name'] ?? ''),
            htmlUrl:       (string) ($row['html_url']  ?? ''),
            description:   $row['description'] ?? null,
            stars:         (int) ($row['stargazers_count'] ?? 0),
            pushedAt:      $row['pushed_at']  ?? null,
            createdAt:     $row['created_at'] ?? null,
            archived:      (bool) ($row['archived'] ?? false),
            fork:          (bool) ($row['fork'] ?? false),
            licenseSpdx:   $row['license']['spdx_id'] ?? null,
            defaultBranch: $row['default_branch'] ?? null,
        );
    }
}
