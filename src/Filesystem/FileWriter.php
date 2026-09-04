<?php

declare(strict_types=1);

namespace Alianet\EnvSync\Filesystem;

interface FileWriter
{
    public function write(string $path, string $contents): void;
}
