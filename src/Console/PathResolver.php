<?php

declare(strict_types=1);

namespace Alianet\EnvSync\Console;

use Alianet\EnvSync\Application\SyncConfiguration;

final class PathResolver
{
    private const ENV_EXAMPLE = '.env.example';
    private const ENV_DIST = '.env.dist';

    /** @return array{string, string} */
    public function resolve(?string $template, ?string $target, SyncConfiguration $configuration): array
    {
        $template ??= $configuration->template;
        if (null === $template) {
            $template = file_exists(self::ENV_EXAMPLE) || !file_exists(self::ENV_DIST)
                ? self::ENV_EXAMPLE
                : self::ENV_DIST;
        }

        return [$template, $target ?? $configuration->target ?? '.env'];
    }
}
