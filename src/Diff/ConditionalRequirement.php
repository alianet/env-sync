<?php

declare(strict_types=1);

namespace Alianet\EnvSync\Diff;

final readonly class ConditionalRequirement
{
    /**
     * @param list<string> $requiredKeys
     * @param list<string> $requiredChangedKeys
     */
    public function __construct(
        public string $key,
        public string $equals,
        public array $requiredKeys = [],
        public array $requiredChangedKeys = [],
    ) {
    }
}
