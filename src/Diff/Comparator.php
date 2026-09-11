<?php

declare(strict_types=1);

namespace Alianet\EnvSync\Diff;

use Alianet\EnvSync\Document\AssignmentLine;
use Alianet\EnvSync\Document\Document;

final class Comparator
{
    public function compare(Document $template, Document $target, ?ComparisonRules $rules = null): DiffResult
    {
        $rules ??= new ComparisonRules();
        $templateAssignments = $template->assignments();
        $targetAssignments = $target->assignments();
        $templateKeys = array_keys($templateAssignments);
        $targetKeys = array_keys($targetAssignments);
        [$requiredKeys, $requiredChangedKeys, $unmatchedConditionKeys] = $this->resolveRequirements(
            $templateKeys,
            $targetAssignments,
            $rules,
        );

        return new DiffResult(
            $this->findMissingKeys($requiredKeys, $targetAssignments),
            $this->findExtraKeys($targetKeys, $templateKeys, $rules),
            $template->duplicateKeys(),
            $target->duplicateKeys(),
            $this->findUnchangedRequiredKeys($requiredChangedKeys, $templateAssignments, $targetAssignments),
            $unmatchedConditionKeys,
        );
    }

    /**
     * @param list<string>                        $templateKeys
     * @param array<string, list<AssignmentLine>> $targetAssignments
     *
     * @return array{list<string>, list<string>, list<string>}
     */
    private function resolveRequirements(
        array $templateKeys,
        array $targetAssignments,
        ComparisonRules $rules,
    ): array {
        $requiredKeys = array_values(array_diff($templateKeys, $rules->conditionalKeys()));
        $requiredChangedKeys = $rules->requiredChangedKeys;
        $conditionKeys = [];
        $matchedConditionKeys = [];

        foreach ($rules->conditionalRequirements as $requirement) {
            $conditionKeys[$requirement->key] = true;
            if (isset($targetAssignments[$requirement->key]) && $requirement->equals === $targetAssignments[$requirement->key][0]->value) {
                $matchedConditionKeys[$requirement->key] = true;
                $requiredKeys = array_merge($requiredKeys, $requirement->requiredKeys);
                $requiredChangedKeys = array_merge($requiredChangedKeys, $requirement->requiredChangedKeys);
            }
        }

        $unmatchedConditionKeys = array_values(array_filter(
            array_keys($conditionKeys),
            static fn (string $key): bool => isset($targetAssignments[$key]) && !isset($matchedConditionKeys[$key]),
        ));

        return [$requiredKeys, $requiredChangedKeys, $unmatchedConditionKeys];
    }

    /**
     * @param list<string>                        $requiredKeys
     * @param array<string, list<AssignmentLine>> $targetAssignments
     *
     * @return list<string>
     */
    private function findMissingKeys(array $requiredKeys, array $targetAssignments): array
    {
        return array_values(array_filter(
            array_keys(array_fill_keys($requiredKeys, true)),
            static fn (string $key): bool => !isset($targetAssignments[$key]),
        ));
    }

    /**
     * @param list<string>                        $requiredChangedKeys
     * @param array<string, list<AssignmentLine>> $templateAssignments
     * @param array<string, list<AssignmentLine>> $targetAssignments
     *
     * @return list<string>
     */
    private function findUnchangedRequiredKeys(
        array $requiredChangedKeys,
        array $templateAssignments,
        array $targetAssignments,
    ): array {
        return array_values(array_filter(
            array_keys(array_fill_keys($requiredChangedKeys, true)),
            static fn (string $key): bool => isset($templateAssignments[$key], $targetAssignments[$key])
                && $templateAssignments[$key][0]->value === $targetAssignments[$key][0]->value,
        ));
    }

    /**
     * @param list<string> $targetKeys
     * @param list<string> $templateKeys
     *
     * @return list<string>
     */
    private function findExtraKeys(array $targetKeys, array $templateKeys, ComparisonRules $rules): array
    {
        return array_values(array_filter(
            array_diff($targetKeys, $templateKeys),
            static fn (string $key): bool => !$rules->allowsExtra($key),
        ));
    }
}
