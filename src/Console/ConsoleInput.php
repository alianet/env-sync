<?php

declare(strict_types=1);

namespace Alianet\EnvSync\Console;

final readonly class ConsoleInput
{
    public function __construct(
        public ?string $command,
        public ?string $template,
        public ?string $target,
        public bool $dryRun,
        public bool $verbose,
        public bool $help,
        public bool $version,
        public string $format,
        public ?string $configuration,
    ) {
    }
}
