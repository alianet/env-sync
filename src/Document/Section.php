<?php

declare(strict_types=1);

namespace Alianet\EnvSync\Document;

final readonly class Section
{
    /** @param list<Line> $lines */
    public function __construct(
        public int $start,
        public int $end,
        public array $lines,
    ) {
    }

    /** @return array<string, int> */
    public function assignmentIndexes(): array
    {
        $indexes = [];
        foreach ($this->lines as $offset => $line) {
            if ($line instanceof AssignmentLine) {
                $indexes[$line->key] = $this->start + $offset;
            }
        }

        return $indexes;
    }

    public function heading(): ?string
    {
        $comments = [];
        foreach ($this->lines as $line) {
            if ($line instanceof AssignmentLine) {
                break;
            }
            if ($line instanceof CommentLine) {
                $comments[] = trim($line->content);
            }
        }

        return [] === $comments ? null : implode("\n", $comments);
    }
}
