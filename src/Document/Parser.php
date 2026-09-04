<?php

declare(strict_types=1);

namespace Alianet\EnvSync\Document;

use Alianet\EnvSync\Exception\ParseException;

final class Parser
{
    public function parse(string $contents): Document
    {
        if ('' === $contents) {
            return new Document([]);
        }

        preg_match_all('/.*?(?:\r\n|\n|\r|$)/', $contents, $matches);
        $lines = [];
        foreach ($matches[0] as $index => $raw) {
            if ('' === $raw) {
                continue;
            }
            preg_match('/(\r\n|\n|\r)$/', $raw, $endingMatch);
            $ending = $endingMatch[1] ?? '';
            $content = '' === $ending ? $raw : substr($raw, 0, -\strlen($ending));
            $lines[] = $this->parseLine($content, $ending, $index + 1);
        }

        return new Document($lines);
    }

    private function parseLine(string $content, string $ending, int $number): Line
    {
        if (1 === preg_match('/^\s*$/', $content)) {
            return new BlankLine($content, $ending);
        }
        if (1 === preg_match('/^\s*#/', $content)) {
            return new CommentLine($content, $ending);
        }
        if (1 !== preg_match('/^\s*(?:export\s+)?([A-Za-z_][A-Za-z0-9_.-]*)\s*=\s*(.*)$/', $content, $match)) {
            throw new ParseException($number, 'unsupported syntax; expected a comment, blank line, or KEY=value assignment');
        }

        $this->validateValue($match[2], $number);

        return new AssignmentLine($content, $ending, $match[1]);
    }

    private function validateValue(string $value, int $number): void
    {
        if ('' === $value) {
            return;
        }

        if (!\in_array($value[0], ["'", '"'], true)) {
            $this->validateUnquotedValue($value, $number);

            return;
        }

        $quote = $value[0];
        $escaped = false;
        $closing = null;
        for ($i = 1, $length = \strlen($value); $i < $length; ++$i) {
            if ('"' === $quote && '\\' === $value[$i] && !$escaped) {
                $escaped = true;
                continue;
            }
            if ($value[$i] === $quote && !$escaped) {
                $closing = $i;
                break;
            }
            $escaped = false;
        }
        if (null === $closing) {
            throw new ParseException($number, 'unterminated quoted value');
        }
        $trailing = substr($value, $closing + 1);
        if (1 !== preg_match('/^\s*(?:#.*)?$/', $trailing)) {
            throw new ParseException($number, 'unexpected content after quoted value');
        }
    }

    private function validateUnquotedValue(string $value, int $number): void
    {
        $unquotedValue = rtrim($value, " \t");
        if (str_starts_with($value, '#')) {
            $unquotedValue = '';
        } elseif (1 === preg_match('/^(.*?)[ \t]+#/', $value, $match)) {
            $unquotedValue = $match[1];
        }

        if (str_ends_with($unquotedValue, '\\')) {
            throw new ParseException($number, 'backslash continuation is not supported');
        }
        if (str_contains($unquotedValue, '$(') || 1 === preg_match('/[;|&<>`]/', $unquotedValue)) {
            throw new ParseException($number, 'shell commands are not supported');
        }
        if (1 !== preg_match('/^(?:#.*|\S+(?:[ \t]+(?:#.*)?)?)$/', $value)) {
            throw new ParseException($number, 'whitespace in an unquoted value is not supported');
        }
    }
}
