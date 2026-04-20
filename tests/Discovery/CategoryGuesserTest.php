<?php declare(strict_types=1);
namespace AwesomeList\Tests\Discovery;

use AwesomeList\Discovery\CategoryGuesser;
use AwesomeList\Discovery\RepoSummary;
use PHPUnit\Framework\TestCase;

final class CategoryGuesserTest extends TestCase
{
    private CategoryGuesser $guesser;

    protected function setUp(): void
    {
        $this->guesser = new CategoryGuesser();
    }

    public function test_payment_keyword_wins(): void
    {
        $this->assertSame(
            'extensions/payment.yml',
            $this->guesser->guess($this->repo('vendor/stripe-module', 'Stripe payments for Magento 2.')),
        );
    }

    public function test_first_rule_wins_on_multi_keyword_match(): void
    {
        // Description contains both "payment" (payment.yml) and "admin" (adminhtml.yml).
        // Payment rule appears earlier in the list, so it wins.
        $this->assertSame(
            'extensions/payment.yml',
            $this->guesser->guess($this->repo('vendor/x', 'Payment admin grid extension.')),
        );
    }

    public function test_pwa_keyword_from_fullname(): void
    {
        $this->assertSame(
            'extensions/pwa.yml',
            $this->guesser->guess($this->repo('hyva-themes/some-component', 'Frontend component.')),
        );
    }

    public function test_case_insensitive(): void
    {
        $this->assertSame(
            'extensions/security.yml',
            $this->guesser->guess($this->repo('owner/x', 'SECURITY patch helper.')),
        );
    }

    public function test_falls_back_to_triage(): void
    {
        $this->assertSame(
            'extensions/_triage.yml',
            $this->guesser->guess($this->repo('owner/x', 'Something bland and uncategorisable.')),
        );
    }

    private function repo(string $fullName, ?string $description): RepoSummary
    {
        return new RepoSummary(
            fullName:      $fullName,
            htmlUrl:       "https://github.com/$fullName",
            description:   $description,
            stars:         50,
            pushedAt:      '2026-04-01T00:00:00Z',
            createdAt:     '2024-01-01T00:00:00Z',
            archived:      false,
            fork:          false,
            licenseSpdx:   'MIT',
            defaultBranch: 'main',
        );
    }
}
