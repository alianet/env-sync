<?php

declare(strict_types=1);

namespace Alianet\EnvSync\Document;

final readonly class Document
{
    /** @param list<Line> $lines */
    public function __construct(public array $lines)
    {
    }

    /** @return array<string, list<AssignmentLine>> */
    public function assignments(): array
    {
        $result = [];
        foreach ($this->lines as $line) {
            if ($line instanceof AssignmentLine) {
                $result[$line->key][] = $line;
            }
        }

        return $result;
    }

    /** @return list<string> */
    public function duplicateKeys(): array
    {
        return array_keys(array_filter($this->assignments(), static fn (array $lines): bool => \count($lines) > 1));
    }

    /** @return list<Section> */
    public function sections(): array
    {
        $sections = [];
        $start = null;
        foreach ($this->lines as $index => $line) {
            if ($line instanceof BlankLine) {
                if (null !== $start) {
                    $sections[] = new Section($start, $index, \array_slice($this->lines, $start, $index - $start));
                    $start = null;
                }

                continue;
            }
            $start ??= $index;
        }
        if (null !== $start) {
            $sections[] = new Section($start, \count($this->lines), \array_slice($this->lines, $start));
        }

        return $sections;
    }

    public function render(): string
    {
        return implode('', array_map(static fn (Line $line): string => $line->render(), $this->lines));
    }

    public function preferredLineEnding(): string
    {
        foreach ($this->lines as $line) {
            if ('' !== $line->ending) {
                return $line->ending;
            }
        }

        return "\n";
    }
}
