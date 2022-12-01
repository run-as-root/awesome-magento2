<?php declare(strict_types=1);

namespace AwesomeList\Parser;

use RuntimeException;

class GenericCsvList implements ParserInterface
{
    private string $filename;

    /**
     * @param string $filename
     * @return void
     */
    public function setFilename(string $filename)
    {
        $this->filename = $filename;
    }

    /**
     * @return string
     */
    public function parseToMarkdown(): string
    {
        $file = fopen($this->filename, 'r');
        $markdownLines = [];
        while (($line = fgetcsv($file)) !== FALSE) {
            $name = $line[0];
            $url = $line[1];
            $description = $line[2];

            if (empty($name)) {
                throw new RuntimeException('Line should have a name');
            }


            if ($url) {
                $name = "[$name]($url)";
            }

            $markdownLine = "- $name";

            if ($description) {
                $markdownLine .= " - $description";
            }

            $markdownLines[] = $markdownLine;
        }

        fclose($file);
        return implode("\n", $markdownLines);
    }
}
