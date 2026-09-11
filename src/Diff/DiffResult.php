<?php

declare(strict_types=1);

namespace Alianet\EnvSync\Diff;

final readonly class DiffResult
{
    /**
     * @param list<string> $missing
     * @param list<string> $extra
     * @param list<string> $templateDuplicates
     * @param list<string> $targetDuplicates
     * @param list<string> $unchangedRequired
     * @param list<string> $unmatchedConditionKeys
     */
    public function __construct(
        public array $missing,
        public array $extra,
        public array $templateDuplicates,
        public array $targetDuplicates,
        public array $unchangedRequired,
        public array $unmatchedConditionKeys = [],
    ) {
    }

    public function hasDifferences(): bool
    {
        return [] !== $this->missing
            || [] !== $this->extra
            || [] !== $this->templateDuplicates
            || [] !== $this->targetDuplicates
            || [] !== $this->unchangedRequired
            || [] !== $this->unmatchedConditionKeys;
    }
}
