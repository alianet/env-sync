<?php

declare(strict_types=1);

namespace Alianet\EnvSync\Application;

final readonly class SyncConfiguration
{
    /**
     * @param list<string> $allowedExtraKeys
     * @param list<string> $allowedExtraPatterns
     */
    public function __construct(
        public ?string $template = null,
        public ?string $target = null,
        public array $allowedExtraKeys = [],
        public array $allowedExtraPatterns = [],
    ) {
    }
}
