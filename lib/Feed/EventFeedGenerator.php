<?php declare(strict_types=1);
namespace AwesomeList\Feed;

use AwesomeList\Entry;
use AwesomeList\EntryType;
use AwesomeList\YamlEntryLoader;
use DateTimeImmutable;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;

final class EventFeedGenerator
{
    public function __construct(
        private readonly YamlEntryLoader $loader,
        private readonly DateTimeImmutable $now,
    ) {}

    /** @return array{ical: string, json: string} */
    public function generate(string $eventsDir): array
    {
        $events = $this->loadEvents($eventsDir);
        return [
            'ical' => $this->toIcal($events),
            'json' => $this->toJson($events),
        ];
    }

    /** @param Entry[] $events */
    public function toIcal(array $events): string
    {
        $lines = [
            'BEGIN:VCALENDAR',
            'VERSION:2.0',
            'PRODID:-//awesome-magento2//EN',
            'X-WR-CALNAME:Awesome Magento 2 Events',
        ];
        foreach ($events as $event) {
            $nextDate = $event->typeSpecific['next_date'] ?? null;
            if (!is_string($nextDate)) {
                continue;
            }
            $lines[] = 'BEGIN:VEVENT';
            $lines[] = 'UID:' . sha1((string) ($event->url ?? $event->name)) . '@awesome-magento2';
            $lines[] = 'DTSTAMP:' . $this->now->format('Ymd\THis\Z');
            $lines[] = 'DTSTART;VALUE=DATE:' . str_replace('-', '', $nextDate);
            $lines[] = 'SUMMARY:' . $this->escape($event->name);
            if ($event->description !== null) {
                $lines[] = 'DESCRIPTION:' . $this->escape($event->description);
            }
            if ($event->url !== null) {
                $lines[] = 'URL:' . $event->url;
            }
            $location = $this->formatLocation($event->typeSpecific['location'] ?? null);
            if ($location !== null) {
                $lines[] = 'LOCATION:' . $this->escape($location);
            }
            if (($event->typeSpecific['recurring'] ?? null) === 'annual') {
                $lines[] = 'RRULE:FREQ=YEARLY';
            }
            $lines[] = 'END:VEVENT';
        }
        $lines[] = 'END:VCALENDAR';
        return implode("\r\n", $lines) . "\r\n";
    }

    /** @param Entry[] $events */
    public function toJson(array $events): string
    {
        $payload = [];
        foreach ($events as $event) {
            $payload[] = [
                'name'        => $event->name,
                'url'         => $event->url,
                'description' => $event->description,
                'next_date'   => $event->typeSpecific['next_date'] ?? null,
                'recurring'   => $event->typeSpecific['recurring'] ?? null,
                'location'    => $event->typeSpecific['location'] ?? null,
                'organizers'  => $event->typeSpecific['organizers'] ?? [],
            ];
        }
        return json_encode($payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) . "\n";
    }

    /** @return Entry[] */
    private function loadEvents(string $dir): array
    {
        if (!is_dir($dir)) {
            return [];
        }
        // Collect + sort filenames so output is deterministic across OSes.
        $files = [];
        $it = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($dir));
        foreach ($it as $f) {
            if ($f->isFile() && $f->getExtension() === 'yml') {
                $files[] = $f->getPathname();
            }
        }
        sort($files);

        $events = [];
        foreach ($files as $path) {
            foreach ($this->loader->load($path) as $entry) {
                if ($entry->type === EntryType::Event) {
                    $events[] = $entry;
                }
            }
        }
        return $events;
    }

    private function formatLocation(mixed $loc): ?string
    {
        if (!is_array($loc)) {
            return null;
        }
        $parts = array_filter([$loc['city'] ?? null, $loc['country'] ?? null]);
        return $parts === [] ? null : implode(', ', $parts);
    }

    private function escape(string $s): string
    {
        return str_replace([',', ';', "\n"], ['\\,', '\\;', '\\n'], $s);
    }
}
