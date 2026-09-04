<?php

declare(strict_types=1);

namespace Alianet\EnvSync\Document;

final class Updater
{
    public function update(Document $template, Document $target): UpdateResult
    {
        if ([] !== $template->duplicateKeys() || [] !== $target->duplicateKeys()) {
            throw new \InvalidArgumentException('Cannot safely update a document containing duplicate keys.');
        }

        $targetAssignments = $target->assignments();
        $missing = [];
        foreach ($template->assignments() as $key => $assignments) {
            if (!isset($targetAssignments[$key])) {
                $missing[$key] = $assignments[0];
            }
        }
        if ([] === $missing) {
            return new UpdateResult($target, []);
        }

        $ending = $target->preferredLineEnding();
        $lines = $target->lines;
        $insertions = [];
        $placed = [];
        $targetSections = $target->sections();
        foreach ($template->sections() as $templateSection) {
            $sectionMissing = array_intersect_key($missing, $templateSection->assignmentIndexes());
            if ([] === $sectionMissing) {
                continue;
            }
            $targetSection = $this->matchingSection($templateSection, $targetSections);
            if (null === $targetSection) {
                continue;
            }

            $targetIndexes = $targetSection->assignmentIndexes();
            $templateKeys = array_keys($templateSection->assignmentIndexes());
            foreach ($templateKeys as $position => $key) {
                if (!isset($sectionMissing[$key])) {
                    continue;
                }
                $insertionIndex = $targetSection->end;
                for ($next = $position + 1, $count = \count($templateKeys); $next < $count; ++$next) {
                    if (isset($targetIndexes[$templateKeys[$next]])) {
                        $insertionIndex = $targetIndexes[$templateKeys[$next]];
                        break;
                    }
                }
                $insertions[$insertionIndex][] = new AssignmentLine($sectionMissing[$key]->content, $ending, $key);
                $placed[$key] = true;
            }
        }

        $fallback = array_diff_key($missing, $placed);
        if ([] !== $fallback) {
            $insertionIndex = \count($lines);
            if ([] !== $lines && !$lines[array_key_last($lines)] instanceof BlankLine) {
                $insertions[$insertionIndex][] = new BlankLine('', $ending);
            }
            $insertions[$insertionIndex][] = new CommentLine('# Added by env-sync', $ending);
            foreach ($fallback as $key => $assignment) {
                $insertions[$insertionIndex][] = new AssignmentLine($assignment->content, $ending, $key);
            }
        }

        if (isset($insertions[\count($lines)]) && [] !== $lines && '' === $lines[array_key_last($lines)]->ending) {
            $lastIndex = array_key_last($lines);
            $lines[$lastIndex] = $this->withEnding($lines[$lastIndex], $ending);
        }

        $updated = [];
        for ($index = 0, $count = \count($lines); $index <= $count; ++$index) {
            foreach ($insertions[$index] ?? [] as $line) {
                $updated[] = $line;
            }
            if ($index < $count) {
                $updated[] = $lines[$index];
            }
        }

        return new UpdateResult(new Document($updated), array_keys($missing));
    }

    /** @param list<Section> $candidates */
    private function matchingSection(Section $template, array $candidates): ?Section
    {
        $templateAssignments = $template->assignmentIndexes();
        $best = null;
        $bestOverlap = 0;
        foreach ($candidates as $candidate) {
            $overlap = \count(array_intersect_key($templateAssignments, $candidate->assignmentIndexes()));
            if ($overlap > $bestOverlap) {
                $best = $candidate;
                $bestOverlap = $overlap;
            }
        }
        if (null !== $best) {
            return $best;
        }

        $heading = $template->heading();
        if (null === $heading) {
            return null;
        }
        $matchingHeadings = array_values(array_filter(
            $candidates,
            static fn (Section $candidate): bool => $heading === $candidate->heading(),
        ));

        return 1 === \count($matchingHeadings) ? $matchingHeadings[0] : null;
    }

    private function withEnding(Line $line, string $ending): Line
    {
        return match (true) {
            $line instanceof AssignmentLine => new AssignmentLine($line->content, $ending, $line->key),
            $line instanceof CommentLine => new CommentLine($line->content, $ending),
            default => new BlankLine($line->content, $ending),
        };
    }
}
