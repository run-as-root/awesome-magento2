<?php declare(strict_types=1);
namespace AwesomeList;

use InvalidArgumentException;

final class Entry
{
    public function __construct(
        public readonly string $name,
        public readonly ?string $url,
        public readonly ?string $description,
        public readonly EntryType $type,
        public readonly string $added,
        public readonly bool $pinned = false,
        public readonly ?string $pinReason = null,
        public readonly array $typeSpecific = [],
    ) {
        if ($name === '') {
            throw new InvalidArgumentException('Entry name must not be empty');
        }
        if ($url === null && $type !== EntryType::Archive) {
            throw new InvalidArgumentException("Entry '$name' of type {$type->value} requires a url");
        }
    }
}
