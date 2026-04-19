<?php declare(strict_types=1);
namespace AwesomeList\Tests\Rendering;

use AwesomeList\Rendering\BadgeRenderer;
use PHPUnit\Framework\TestCase;

final class BadgeRendererTest extends TestCase
{
    public function test_no_signals_yields_empty_string(): void
    {
        $this->assertSame('', (new BadgeRenderer())->render(null));
        $this->assertSame('', (new BadgeRenderer())->render([]));
    }

    public function test_vitality_hot_yields_fire(): void
    {
        $this->assertSame(' 🔥', (new BadgeRenderer())->render(['vitality_hot' => true]));
    }

    public function test_both_signals_yield_both_badges_in_stable_order(): void
    {
        $this->assertSame(
            ' 🔥 🫡',
            (new BadgeRenderer())->render([
                'vitality_hot' => true,
                'actively_maintained' => true,
            ])
        );
    }
}
