<?php declare(strict_types=1);
namespace AwesomeList\Tests\Discovery;

use AwesomeList\Discovery\CandidateLog;
use PHPUnit\Framework\TestCase;

final class CandidateLogTest extends TestCase
{
    public function test_missing_file_yields_empty_log(): void
    {
        $log = CandidateLog::loadOrEmpty(__DIR__ . '/../fixtures/state/nope.json');
        $this->assertFalse($log->has('https://x'));
        $this->assertNull($log->statusOf('https://x'));
    }

    public function test_mark_transitions(): void
    {
        $log = CandidateLog::loadOrEmpty(__DIR__ . '/../fixtures/state/nope.json');
        $log = $log->markPending('https://github.com/a/b', 'extensions/_triage.yml');
        $this->assertSame('pending', $log->statusOf('https://github.com/a/b'));
        $log = $log->markAccepted('https://github.com/a/b');
        $this->assertSame('accepted', $log->statusOf('https://github.com/a/b'));
    }

    public function test_save_round_trips(): void
    {
        $path = tempnam(sys_get_temp_dir(), 'clog') . '.json';
        $log = CandidateLog::loadOrEmpty($path)
            ->markPending('https://github.com/a/b', 'extensions/search.yml')
            ->markRejected('https://github.com/c/d');
        $log->save($path);

        $reloaded = CandidateLog::loadOrEmpty($path);
        $this->assertSame('pending',  $reloaded->statusOf('https://github.com/a/b'));
        $this->assertSame('rejected', $reloaded->statusOf('https://github.com/c/d'));
        unlink($path);
    }
}
