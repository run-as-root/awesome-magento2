<?php declare(strict_types=1);

namespace AwesomeList\Parser;

interface ParserInterface
{
    /**
     * @param string $filename
     * @return void
     */
    public function setFilename(string $filename);

    /**
     * @return string
     */
    public function parseToMarkdown(): string;
}
