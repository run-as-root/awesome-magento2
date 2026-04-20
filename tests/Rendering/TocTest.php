<?php declare(strict_types=1);
namespace AwesomeList\Tests\Rendering;

use AwesomeList\Rendering\Toc;
use PHPUnit\Framework\TestCase;

final class TocTest extends TestCase
{
    public function test_emits_bullet_list_from_level_2_headings(): void
    {
        $md = <<<MD
# Title

## Legend

text

## What is Magento?

text

## Tools

text

## Open Source Extensions

text
MD;
        $toc = (new Toc())->render($md);

        $this->assertSame(
            "- [Legend](#legend)\n"
          . "- [What is Magento?](#what-is-magento)\n"
          . "- [Tools](#tools)\n"
          . "- [Open Source Extensions](#open-source-extensions)",
            $toc,
        );
    }

    public function test_ignores_fenced_code_blocks_and_table_of_contents_heading(): void
    {
        $md = <<<MD
## Table of Contents

- [First](#first)

## First

```
## Fake heading inside code block
```

## Second
MD;
        $toc = (new Toc())->render($md);

        $this->assertSame("- [First](#first)\n- [Second](#second)", $toc);
    }

    public function test_slugifies_emoji_and_punctuation(): void
    {
        $md = "## Events: Meet the community 🎉";
        $toc = (new Toc())->render($md);

        $this->assertSame('- [Events: Meet the community 🎉](#events-meet-the-community)', $toc);
    }
}
