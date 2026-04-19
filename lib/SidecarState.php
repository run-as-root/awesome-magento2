<?php declare(strict_types=1);
namespace AwesomeList;

final class SidecarState
{
    private function __construct(private readonly array $byUrl) {}

    public static function loadOrEmpty(string $path): self
    {
        if (!is_file($path)) {
            return new self([]);
        }
        $data = json_decode((string) file_get_contents($path), true, flags: JSON_THROW_ON_ERROR);
        return new self(is_array($data) ? $data : []);
    }

    public function forUrl(string $url): array
    {
        return $this->byUrl[$url] ?? [];
    }

    public function signalsFor(string $url): ?array
    {
        return $this->byUrl[$url]['signals'] ?? null;
    }
}
