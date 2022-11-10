<?php declare(strict_types=1);

namespace AwesomeList;

use AwesomeList\Parser\ParserInterface;
use RuntimeException;

class MarkdownGenerator
{
    public function generate(string $contentDirectory): string
    {
        $mainFile = $contentDirectory . '/main.md';
        if (!is_file($mainFile) || !is_readable($mainFile)) {
            throw new RuntimeException('Main file "' . $mainFile . '" is unreadable');
        }

        $mainContents = file_get_contents($mainFile);
        if (preg_match_all('/\{%(.*)%\}/', $mainContents, $matches)) {
            foreach ($matches[1] as $matchIndex => $match) {
                $tagData = $this->parseTag($match);
                if (false === $this->validateTagData($tagData, $contentDirectory)) {
                    continue;
                }

                $file = $tagData['file'];

                /** @var ParserInterface $parser */
                $parser = (new ParserFactory)->create($tagData['parser']);
                $parser->setFilename($contentDirectory . '/' . $file);
                $markdown = $parser->parseToMarkdown();
                $mainContents = str_replace($matches[0][$matchIndex], $markdown, $mainContents);
            }
        }

        return $mainContents;
    }

    /**
     * @param string $tag
     * @return string[]
     */
    private function parseTag(string $tag): array
    {
        $tagData = [];
        $tag = trim($tag);
        $tagParts = explode(' ', $tag);
        foreach ($tagParts as $tagPart) {
            $tagPart = explode('=', $tagPart);
            $tagPartName = trim($tagPart[0]);
            $tagPartValue = str_replace(['"', ' '], '', $tagPart[1]);
            $tagData[$tagPartName] = $tagPartValue;
        }

        return $tagData;
    }

    private function validateTagData(array $tagData, string $contentDirectory): bool
    {
        if (!isset($tagData['file'])) {
            throw new RuntimeException('No file set in tag data: ' . var_export($tagData, true));
        }

        if (!file_exists($contentDirectory . '/' . $tagData['file'])) {
            throw new RuntimeException('File "' . $tagData['file'] . '" could not be found in "' . $contentDirectory . '"');
        }

        return true;
    }
}