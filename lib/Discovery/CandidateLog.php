<?php declare(strict_types=1);
namespace AwesomeList\Discovery;

final class CandidateLog
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

    public function has(string $url): bool
    {
        return isset($this->byUrl[$url]);
    }

    public function statusOf(string $url): ?string
    {
        return $this->byUrl[$url]['status'] ?? null;
    }

    public function suggestedYaml(string $url): ?string
    {
        return $this->byUrl[$url]['suggested_yaml'] ?? null;
    }

    public function markPending(string $url, string $suggestedYaml): self
    {
        $byUrl = $this->byUrl;
        $byUrl[$url] = [
            'status'         => 'pending',
            'suggested_yaml' => $suggestedYaml,
            'discovered_at'  => $this->byUrl[$url]['discovered_at'] ?? gmdate('Y-m-d\TH:i:s\Z'),
        ];
        return new self($byUrl);
    }

    public function markAccepted(string $url): self
    {
        return $this->transition($url, 'accepted');
    }

    public function markRejected(string $url): self
    {
        return $this->transition($url, 'rejected');
    }

    public function save(string $path): void
    {
        $parent = dirname($path);
        if (!is_dir($parent)) {
            mkdir($parent, 0755, true);
        }
        file_put_contents($path, json_encode($this->byUrl, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . "\n");
    }

    /** @return array<string, array<string, mixed>> */
    public function all(): array
    {
        return $this->byUrl;
    }

    private function transition(string $url, string $status): self
    {
        $byUrl = $this->byUrl;
        $byUrl[$url] = ($byUrl[$url] ?? []) + ['status' => $status, 'decided_at' => gmdate('Y-m-d\TH:i:s\Z')];
        $byUrl[$url]['status']     = $status;
        $byUrl[$url]['decided_at'] = gmdate('Y-m-d\TH:i:s\Z');
        return new self($byUrl);
    }
}
