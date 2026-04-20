<?php declare(strict_types=1);
namespace AwesomeList\Tests\Feed;

use AwesomeList\Feed\EventFeedGenerator;
use AwesomeList\YamlEntryLoader;
use DateTimeImmutable;
use PHPUnit\Framework\TestCase;

final class EventFeedGeneratorTest extends TestCase
{
    private EventFeedGenerator $gen;

    protected function setUp(): void
    {
        $this->gen = new EventFeedGenerator(
            new YamlEntryLoader(),
            new DateTimeImmutable('2026-04-20T02:00:00Z'),
        );
    }

    public function test_ical_includes_event_with_next_date_and_skips_events_without_one(): void
    {
        $out = $this->gen->generate(__DIR__ . '/../fixtures/events');

        $this->assertStringContainsString('BEGIN:VCALENDAR', $out['ical']);
        $this->assertStringContainsString('SUMMARY:TestEvent With Date', $out['ical']);
        $this->assertStringContainsString('DTSTART;VALUE=DATE:20261015', $out['ical']);
        $this->assertStringContainsString('URL:https://example.com/event', $out['ical']);
        $this->assertStringContainsString('LOCATION:Cologne\\, DE', $out['ical']);
        $this->assertStringContainsString('RRULE:FREQ=YEARLY', $out['ical']);
        $this->assertStringContainsString('END:VCALENDAR', $out['ical']);

        // Event with no next_date does not appear as a VEVENT.
        $this->assertStringNotContainsString('TestEvent No Date', $out['ical']);
        // Non-event entries are filtered out entirely.
        $this->assertStringNotContainsString('Non-Event Entry', $out['ical']);
    }

    public function test_json_includes_every_event_even_without_a_date(): void
    {
        $out = $this->gen->generate(__DIR__ . '/../fixtures/events');
        $decoded = json_decode($out['json'], true);

        $this->assertCount(2, $decoded);
        $this->assertSame('TestEvent With Date', $decoded[0]['name']);
        $this->assertSame('2026-10-15', $decoded[0]['next_date']);
        $this->assertSame(['city' => 'Cologne', 'country' => 'DE'], $decoded[0]['location']);
        $this->assertSame(['Jisse Reitsma'], $decoded[0]['organizers']);

        $this->assertSame('TestEvent No Date', $decoded[1]['name']);
        $this->assertNull($decoded[1]['next_date']);
    }

    public function test_missing_events_directory_yields_empty_calendar_and_empty_array(): void
    {
        $out = $this->gen->generate(__DIR__ . '/../fixtures/events/does-not-exist');

        $this->assertStringContainsString('BEGIN:VCALENDAR', $out['ical']);
        $this->assertStringContainsString('END:VCALENDAR', $out['ical']);
        $this->assertStringNotContainsString('BEGIN:VEVENT', $out['ical']);
        $this->assertSame("[]\n", $out['json']);
    }
}
