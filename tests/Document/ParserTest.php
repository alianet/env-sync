<?php

declare(strict_types=1);

namespace Alianet\EnvSync\Tests\Document;

use Alianet\EnvSync\Document\AssignmentLine;
use Alianet\EnvSync\Document\BlankLine;
use Alianet\EnvSync\Document\CommentLine;
use Alianet\EnvSync\Document\Parser;
use Alianet\EnvSync\Exception\ParseException;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class ParserTest extends TestCase
{
    /** @param list<string> $keys */
    #[DataProvider('documents')]
    public function testPreservesSupportedDocuments(string $contents, array $keys): void
    {
        $document = (new Parser())->parse($contents);

        self::assertSame($contents, $document->render());
        self::assertSame($keys, array_keys($document->assignments()));
    }

    /** @return iterable<string, array{string, list<string>}> */
    public static function documents(): iterable
    {
        yield 'comments, blanks, export and empty values' => ["# heading\n\nexport FOO=\nBAR='x' # note\nBAZ=value # note\nQUX=# empty\n", ['FOO', 'BAR', 'BAZ', 'QUX']];
        yield 'hash inside values' => ["ONE=abc#def\nTWO=\"abc#def\" # comment\n", ['ONE', 'TWO']];
        yield 'LF' => ["A=1\nB=2\n", ['A', 'B']];
        yield 'CRLF' => ["A=1\r\nB=2\r\n", ['A', 'B']];
        yield 'empty' => ['', []];
    }

    #[DataProvider('quotedValues')]
    public function testPreservesQuotedValues(string $value): void
    {
        $contents = "VALUE={$value}\n";

        $document = (new Parser())->parse($contents);

        self::assertSame($contents, $document->render());
        self::assertSame(['VALUE'], array_keys($document->assignments()));
    }

    /** @return iterable<string, array{string}> */
    public static function quotedValues(): iterable
    {
        yield 'escaped double quotes' => ['"say \\"hello\\""'];
        yield 'escaped single quote' => ["'it\\'s safe'"];
        yield 'comment after double-quoted value' => ['"value # inside" # outside'];
        yield 'comment after single-quoted value without whitespace' => ["'value'# outside"];
    }

    public function testBuildsStructuralLineTypes(): void
    {
        $lines = (new Parser())->parse("\n# note\nA=1\n")->lines;

        self::assertInstanceOf(BlankLine::class, $lines[0]);
        self::assertInstanceOf(CommentLine::class, $lines[1]);
        self::assertInstanceOf(AssignmentLine::class, $lines[2]);
    }

    public function testBuildsSectionsSeparatedByBlankLines(): void
    {
        $sections = (new Parser())->parse("# App\nAPP_ENV=dev\n\n\n# Database\nDB_HOST=localhost\n")->sections();

        self::assertCount(2, $sections);
        self::assertSame('# App', $sections[0]->heading());
        self::assertSame(['APP_ENV' => 1], $sections[0]->assignmentIndexes());
        self::assertSame('# Database', $sections[1]->heading());
        self::assertSame(['DB_HOST' => 5], $sections[1]->assignmentIndexes());
    }

    public function testReportsLineForUnsupportedSyntax(): void
    {
        $this->expectException(ParseException::class);
        $this->expectExceptionMessage('Line 2: unterminated quoted value');

        (new Parser())->parse("OK=yes\nBROKEN=\"secret\n");
    }

    #[DataProvider('unsupportedUnquotedValues')]
    public function testRejectsUnsupportedUnquotedValues(string $value, string $reason): void
    {
        $this->expectException(ParseException::class);
        $this->expectExceptionMessage('Line 2: '.$reason);

        (new Parser())->parse("OK=yes\nBROKEN={$value}\n");
    }

    /** @return iterable<string, array{string, string}> */
    public static function unsupportedUnquotedValues(): iterable
    {
        yield 'command substitution' => ['$(command)', 'shell commands are not supported'];
        yield 'command separator' => ['value; command', 'shell commands are not supported'];
        yield 'whitespace' => ['two words', 'whitespace in an unquoted value is not supported'];
        yield 'backslash continuation' => ['value\\', 'backslash continuation is not supported'];
    }
}
