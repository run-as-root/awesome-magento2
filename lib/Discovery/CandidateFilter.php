<?php declare(strict_types=1);
namespace AwesomeList\Discovery;

use DateTimeImmutable;

final class CandidateFilter
{
    private const MIN_STARS         = 10;
    private const MAX_STALE_DAYS    = 540;
    private const YOUNG_AGE_MONTHS  = 6;
    private const MIN_VELOCITY      = 2.0; // stars per month

    public function __construct(private readonly DateTimeImmutable $now) {}

    /**
     * @param RepoSummary[] $candidates
     * @return RepoSummary[]
     */
    public function filter(array $candidates, ExistingUrlsIndex $index, CandidateLog $log): array
    {
        return array_values(array_filter(
            $candidates,
            fn(RepoSummary $r): bool => $this->passes($r, $index, $log),
        ));
    }

    private function passes(RepoSummary $r, ExistingUrlsIndex $index, CandidateLog $log): bool
    {
        if ($r->archived || $r->fork || $r->licenseSpdx === null) {
            return false;
        }
        if ($r->stars < self::MIN_STARS) {
            return false;
        }
        if ($r->pushedAt === null || $this->daysSince($r->pushedAt) > self::MAX_STALE_DAYS) {
            return false;
        }
        if (!$this->meetsVelocity($r)) {
            return false;
        }
        if ($index->contains($r->htmlUrl)) {
            return false;
        }
        if ($log->has($r->htmlUrl)) {
            return false;
        }
        return true;
    }

    private function meetsVelocity(RepoSummary $r): bool
    {
        if ($r->createdAt === null) {
            return true;
        }
        $ageMonths = $this->monthsSince($r->createdAt);
        if ($ageMonths < self::YOUNG_AGE_MONTHS) {
            return true; // young repo exemption
        }
        return ($r->stars / max($ageMonths, 1)) > self::MIN_VELOCITY;
    }

    private function daysSince(string $iso): int
    {
        $then = new DateTimeImmutable($iso);
        $diff = $this->now->diff($then);
        return $diff->invert === 1 ? (int) $diff->days : 0;
    }

    private function monthsSince(string $iso): float
    {
        return $this->daysSince($iso) / 30.0;
    }
}
