<?php declare(strict_types=1);
namespace AwesomeList\Tests;

use AwesomeList\SidecarState;
use PHPUnit\Framework\TestCase;

final class SidecarStateTest extends TestCase
{
    public function test_it_returns_empty_array_for_missing_file(): void
    {
        $state = SidecarState::loadOrEmpty(__DIR__ . '/fixtures/state/nope.json');
        $this->assertSame([], $state->forUrl('https://example.com'));
    }

    public function test_it_returns_signals_for_a_known_url(): void
    {
        $state = SidecarState::loadOrEmpty(__DIR__ . '/fixtures/state/enrichment.sample.json');
        $signals = $state->signalsFor('https://github.com/netz98/n98-magerun2');
        $this->assertTrue($signals['vitality_hot']);
        $this->assertFalse($signals['graveyard_candidate']);
    }

    public function test_it_returns_null_for_unknown_url(): void
    {
        $state = SidecarState::loadOrEmpty(__DIR__ . '/fixtures/state/enrichment.sample.json');
        $this->assertNull($state->signalsFor('https://unknown.example'));
    }
}
