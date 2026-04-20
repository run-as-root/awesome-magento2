<?php declare(strict_types=1);
namespace AwesomeList\Tests\Discovery;

use AwesomeList\Discovery\CandidateParser;
use PHPUnit\Framework\TestCase;

final class CandidateParserTest extends TestCase
{
    public function test_parses_checked_and_unchecked_boxes(): void
    {
        $body = <<<MD
<!-- candidates-issue-v1 -->
# Magento 2 Discovery Candidates

## New candidates (3)

- [x] [alpha/beta](https://github.com/alpha/beta) ★42 — A thing. _(suggested: `extensions/_triage.yml`)_
- [ ] [owner/pay](https://github.com/owner/pay) ★15 — Payment. _(suggested: `extensions/payment.yml`)_
- [x] [nodesc/repo](https://github.com/nodesc/repo) ★8 — _no description_ _(suggested: `extensions/search.yml`)_
MD;

        $rows = (new CandidateParser())->parse($body);

        $this->assertCount(3, $rows);
        $this->assertSame('https://github.com/alpha/beta',  $rows[0]['url']);
        $this->assertSame('extensions/_triage.yml',         $rows[0]['suggested_yaml']);
        $this->assertTrue($rows[0]['checked']);

        $this->assertSame('https://github.com/owner/pay',   $rows[1]['url']);
        $this->assertFalse($rows[1]['checked']);

        $this->assertTrue($rows[2]['checked']);
    }

    public function test_ignores_history_section(): void
    {
        $body = <<<MD
## New candidates (1)

- [x] [new/one](https://github.com/new/one) ★10 — thing. _(suggested: `extensions/cms.yml`)_

## Previously decided (2)

<details>
<summary>History</summary>

- ✅ [old/accepted](https://github.com/old/accepted) accepted 2026-04-12
- ❌ [old/rejected](https://github.com/old/rejected) rejected 2026-04-12

</details>
MD;

        $rows = (new CandidateParser())->parse($body);

        $this->assertCount(1, $rows);
        $this->assertSame('https://github.com/new/one', $rows[0]['url']);
    }

    public function test_empty_body_returns_empty_array(): void
    {
        $this->assertSame([], (new CandidateParser())->parse(''));
    }
}
