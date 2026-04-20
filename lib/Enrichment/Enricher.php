<?php declare(strict_types=1);
namespace AwesomeList\Enrichment;

use AwesomeList\YamlEntryLoader;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use RuntimeException;
use Throwable;

final class Enricher
{
    public function __construct(
        private readonly YamlEntryLoader $loader,
        private readonly AdapterFactory $adapters,
        private readonly VitalityRanker $ranker,
    ) {}

    /** @return array<string, array<string, mixed>> sidecar state keyed by url */
    public function enrichDirectory(string $dataDir, string $priorStatePath): array
    {
        if (!is_dir($dataDir)) {
            throw new RuntimeException("Data directory not found: $dataDir");
        }

        $priorState = is_file($priorStatePath)
            ? (json_decode((string) file_get_contents($priorStatePath), true, flags: JSON_THROW_ON_ERROR) ?: [])
            : [];

        $rows = [];
        foreach ($this->yamlFiles($dataDir) as $file) {
            $category = pathinfo($file, PATHINFO_FILENAME);
            foreach ($this->loader->load($file) as $entry) {
                $adapter = $this->adapters->for($entry->type);
                if ($adapter === null || $entry->url === null) {
                    continue;
                }
                try {
                    $result = $adapter->enrich($entry, $priorState[$entry->url] ?? []);
                } catch (Throwable $e) {
                    fwrite(STDERR, "skip {$entry->url}: {$e->getMessage()}\n");
                    continue;
                }
                $rows[$entry->url] = [
                    'category' => $category,
                    'result'   => $result,
                ];
            }
        }

        $ranked = $this->ranker->rank($rows);
        $state  = [];
        foreach ($ranked as $url => $row) {
            $state[$url] = $row['result']->toArray();
        }
        return $state;
    }

    /** @return iterable<string> */
    private function yamlFiles(string $dir): iterable
    {
        $files = [];
        $it = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($dir));
        foreach ($it as $f) {
            if ($f->isFile() && $f->getExtension() === 'yml') {
                $files[] = $f->getPathname();
            }
        }
        sort($files);
        foreach ($files as $path) {
            yield $path;
        }
    }
}
