<?php

declare(strict_types=1);

namespace Alianet\EnvSync\Diff;

final readonly class ComparisonRules
{
    /**
     * @param list<string> $allowedExtraKeys
     * @param list<string> $allowedExtraPatterns
     */
    public function __construct(
        public array $allowedExtraKeys = [],
        public array $allowedExtraPatterns = [],
    ) {
    }

    public function allowsExtra(string $key): bool
    {
        if (\in_array($key, $this->allowedExtraKeys, true)) {
            return true;
        }

        foreach ($this->allowedExtraPatterns as $pattern) {
            $expression = str_replace(['\\*', '\\?'], ['.*', '.'], preg_quote($pattern, '/'));
            if (1 === preg_match('/^'.$expression.'$/D', $key)) {
                return true;
            }
        }

        return false;
    }
}
