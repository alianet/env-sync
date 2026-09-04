<?php

declare(strict_types=1);

namespace Alianet\EnvSync\Diff;

use Alianet\EnvSync\Document\Document;

final class Comparator
{
    public function compare(Document $template, Document $target, ?ComparisonRules $rules = null): DiffResult
    {
        $templateKeys = array_keys($template->assignments());
        $targetKeys = array_keys($target->assignments());
        $rules ??= new ComparisonRules();
        $extraKeys = array_values(array_filter(
            array_diff($targetKeys, $templateKeys),
            static fn (string $key): bool => !$rules->allowsExtra($key),
        ));

        return new DiffResult(
            array_values(array_diff($templateKeys, $targetKeys)),
            $extraKeys,
            $template->duplicateKeys(),
            $target->duplicateKeys(),
        );
    }
}
