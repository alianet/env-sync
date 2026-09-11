<?php

declare(strict_types=1);

namespace Alianet\EnvSync\Application;

use Alianet\EnvSync\Diff\ConditionalRequirement;

final readonly class SyncConfiguration
{
    /**
     * @param list<string>                 $allowedExtraKeys
     * @param list<string>                 $allowedExtraPatterns
     * @param list<string>                 $requiredChangedKeys
     * @param list<ConditionalRequirement> $conditionalRequirements
     */
    public function __construct(
        public ?string $template = null,
        public ?string $target = null,
        public array $allowedExtraKeys = [],
        public array $allowedExtraPatterns = [],
        public array $requiredChangedKeys = [],
        public array $conditionalRequirements = [],
    ) {
    }
}
