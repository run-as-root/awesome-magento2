<?php declare(strict_types=1);

namespace AwesomeList;

use AwesomeList\Parser\ParserInterface;
use RuntimeException;

class ParserFactory
{
    public function create(string $parserClass): ParserInterface
    {
        if (empty($parserClass)) {
            throw new RuntimeException('No parser class given');
        }

        if (!class_exists($parserClass)) {
            throw new RuntimeException('Parser class "' . $parserClass . '" does not exist');
        }

        $parser = new $parserClass;
        if (!$parser instanceof ParserInterface) {
            throw new RuntimeException('Parser class "' . $parserClass . '" is not a \AwesomeList\Parser\ParserInterface');
        }

        return $parser;
    }
}