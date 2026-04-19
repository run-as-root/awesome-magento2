<?php declare(strict_types=1);
namespace AwesomeList\Tests;

use AwesomeList\EntryType;
use PHPUnit\Framework\TestCase;

final class EntryTypeTest extends TestCase
{
    public function test_from_string_resolves_known_types(): void
    {
        $this->assertSame(EntryType::GithubRepo, EntryType::from('github_repo'));
        $this->assertSame(EntryType::Blog, EntryType::from('blog'));
        $this->assertSame(EntryType::Event, EntryType::from('event'));
    }

    public function test_covers_all_nine_types(): void
    {
        $this->assertCount(9, EntryType::cases());
    }
}
