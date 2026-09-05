<?php

declare(strict_types=1);

namespace Alianet\EnvSync\Diff;

final readonly class ComparisonRules
{
    /** @var list<string> */
    private array $allowedExtraExpressions;

    /**
     * @param list<string> $allowedExtraKeys
     * @param list<string> $allowedExtraPatterns
     */
    public function __construct(
        public array $allowedExtraKeys = [],
        public array $allowedExtraPatterns = [],
    ) {
        $this->allowedExtraExpressions = array_map(
            static fn (string $pattern): string => '/^'.str_replace(['\\*', '\\?'], ['.*', '.'], preg_quote($pattern, '/')).'$/D',
            $allowedExtraPatterns,
        );
    }

    public function allowsExtra(string $key): bool
    {
        if (\in_array($key, $this->allowedExtraKeys, true)) {
            return true;
        }

        foreach ($this->allowedExtraExpressions as $expression) {
            if (1 === preg_match($expression, $key)) {
                return true;
            }
        }

        return false;
    }
}
