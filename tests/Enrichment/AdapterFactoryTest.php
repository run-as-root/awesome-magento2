<?php declare(strict_types=1);
namespace AwesomeList\Tests\Enrichment;

use AwesomeList\Enrichment\AdapterFactory;
use AwesomeList\Enrichment\GithubRepoAdapter;
use AwesomeList\EntryType;
use GuzzleHttp\Client;
use PHPUnit\Framework\TestCase;

final class AdapterFactoryTest extends TestCase
{
    public function test_it_returns_github_adapter_for_github_repo_type(): void
    {
        $factory = new AdapterFactory([new GithubRepoAdapter(new Client(), new \DateTimeImmutable())]);
        $this->assertInstanceOf(GithubRepoAdapter::class, $factory->for(EntryType::GithubRepo));
    }

    public function test_it_returns_null_for_unsupported_type(): void
    {
        $factory = new AdapterFactory([new GithubRepoAdapter(new Client(), new \DateTimeImmutable())]);
        $this->assertNull($factory->for(EntryType::Blog));
    }
}
