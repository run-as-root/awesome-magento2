<?php declare(strict_types=1);
namespace AwesomeList\Tests\Rendering;

use AwesomeList\Entry;
use AwesomeList\EntryType;
use AwesomeList\Rendering\EntrySorter;
use AwesomeList\SidecarState;
use PHPUnit\Framework\TestCase;

final class EntrySorterTest extends TestCase
{
    public function test_pinned_first_then_hot_then_maintained_then_rest(): void
    {
        $entries = [
            $this->entry('Plain-250-stars',    'https://x/plain'),
            $this->entry('Hot',                'https://x/hot'),
            $this->entry('Pinned',             'https://x/pinned', pinned: true),
            $this->entry('Maintained',         'https://x/maintained'),
        ];
        $state = $this->state([
            'https://x/hot'        => ['signals' => ['vitality_hot' => true],       'github' => ['stars' => 200]],
            'https://x/maintained' => ['signals' => ['actively_maintained' => true],'github' => ['stars' => 80]],
            'https://x/plain'      => ['signals' => [],                              'github' => ['stars' => 250]],
        ]);

        $sorted = (new EntrySorter())->sort($entries, $state);

        $this->assertSame(
            ['Pinned', 'Hot', 'Maintained', 'Plain-250-stars'],
            array_map(fn(Entry $e): string => $e->name, $sorted),
        );
    }

    public function test_ties_broken_by_stars_descending(): void
    {
        $entries = [
            $this->entry('Low',  'https://x/low'),
            $this->entry('High', 'https://x/high'),
            $this->entry('Mid',  'https://x/mid'),
        ];
        $state = $this->state([
            'https://x/low'  => ['signals' => ['actively_maintained' => true], 'github' => ['stars' => 10]],
            'https://x/high' => ['signals' => ['actively_maintained' => true], 'github' => ['stars' => 500]],
            'https://x/mid'  => ['signals' => ['actively_maintained' => true], 'github' => ['stars' => 100]],
        ]);

        $sorted = (new EntrySorter())->sort($entries, $state);

        $this->assertSame(['High', 'Mid', 'Low'], array_map(fn(Entry $e): string => $e->name, $sorted));
    }

    public function test_score_falls_back_to_packagist_downloads(): void
    {
        $entries = [
            $this->entry('LowDl',  'https://x/pkg-low'),
            $this->entry('HighDl', 'https://x/pkg-high'),
        ];
        $state = $this->state([
            'https://x/pkg-low'  => ['signals' => [], 'packagist' => ['downloads_monthly' => 50]],
            'https://x/pkg-high' => ['signals' => [], 'packagist' => ['downloads_monthly' => 5000]],
        ]);

        $sorted = (new EntrySorter())->sort($entries, $state);

        $this->assertSame(['HighDl', 'LowDl'], array_map(fn(Entry $e): string => $e->name, $sorted));
    }

    public function test_alphabetical_tiebreak_within_equal_score(): void
    {
        $entries = [
            $this->entry('Banana', 'https://x/b'),
            $this->entry('apple',  'https://x/a'),
            $this->entry('Cherry', 'https://x/c'),
        ];
        $state = $this->state([]);

        $sorted = (new EntrySorter())->sort($entries, $state);

        $this->assertSame(['apple', 'Banana', 'Cherry'], array_map(fn(Entry $e): string => $e->name, $sorted));
    }

    public function test_urlless_entry_sorts_to_bottom_by_score_zero(): void
    {
        $archive = new Entry(
            name: 'Archived Person',
            url: null,
            description: null,
            type: EntryType::Archive,
            added: '2017-01-01',
        );
        $maintained = $this->entry('Maintained', 'https://x/m');
        $state = $this->state([
            'https://x/m' => ['signals' => ['actively_maintained' => true], 'github' => ['stars' => 20]],
        ]);

        $sorted = (new EntrySorter())->sort([$archive, $maintained], $state);

        $this->assertSame(['Maintained', 'Archived Person'], array_map(fn(Entry $e): string => $e->name, $sorted));
    }

    private function entry(string $name, ?string $url, bool $pinned = false): Entry
    {
        return new Entry(
            name: $name,
            url: $url,
            description: null,
            type: EntryType::GithubRepo,
            added: '2020-01-01',
            pinned: $pinned,
        );
    }

    /** @param array<string, array<string, mixed>> $data */
    private function state(array $data): SidecarState
    {
        $tmp = tempnam(sys_get_temp_dir(), 'state-') . '.json';
        file_put_contents($tmp, json_encode($data));
        $state = SidecarState::loadOrEmpty($tmp);
        unlink($tmp);
        return $state;
    }
}
