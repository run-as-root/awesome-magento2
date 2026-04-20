<?php declare(strict_types=1);
namespace AwesomeList\Discovery;

use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use Symfony\Component\Yaml\Yaml;

final class ExistingUrlsIndex
{
    private function __construct(private readonly array $urls) {}

    public static function build(string $dataDir): self
    {
        $urls = [];
        if (!is_dir($dataDir)) {
            return new self([]);
        }
        $it = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($dataDir));
        foreach ($it as $f) {
            if (!$f->isFile() || $f->getExtension() !== 'yml') {
                continue;
            }
            $rows = Yaml::parseFile($f->getPathname()) ?? [];
            foreach ($rows as $row) {
                if (!is_array($row) || empty($row['url'])) {
                    continue;
                }
                $urls[self::normalise((string) $row['url'])] = true;
            }
        }
        return new self($urls);
    }

    public function contains(string $url): bool
    {
        return isset($this->urls[self::normalise($url)]);
    }

    private static function normalise(string $url): string
    {
        $url = preg_replace('~[?#].*$~', '', $url) ?? $url;
        $url = preg_replace('~^(https?://)www\.~i', '$1', $url) ?? $url;
        $url = rtrim($url, '/');
        if (preg_match('~^(https?://github\.com/[^/]+/[^/]+?)(?:\.git)?(?:/.*)?$~', $url, $m)) {
            return strtolower($m[1]);
        }
        return strtolower($url);
    }
}
