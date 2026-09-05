<?php

declare(strict_types=1);

namespace Alianet\EnvSync\Filesystem;

use Alianet\EnvSync\Exception\FileWriteException;

final class AtomicFileWriter implements FileWriter
{
    public function write(string $path, string $contents): void
    {
        $this->assertNotSymbolicLink($path);

        $directory = \dirname($path);
        if (!is_dir($directory)) {
            throw new FileWriteException(\sprintf('Directory does not exist: %s', $directory));
        }
        $temporary = tempnam($directory, '.env-sync-');
        if (false === $temporary) {
            throw new FileWriteException(\sprintf('Could not create a temporary file in %s', $directory));
        }

        try {
            if (false === file_put_contents($temporary, $contents, \LOCK_EX)) {
                throw new FileWriteException(\sprintf('Could not write temporary file for %s', $path));
            }
            if (file_exists($path)) {
                $permissions = fileperms($path);
                if (false !== $permissions && !chmod($temporary, $permissions & 0777)) {
                    throw new FileWriteException(\sprintf('Could not preserve permissions for %s', $path));
                }
            }
            $this->assertNotSymbolicLink($path);
            if (!rename($temporary, $path)) {
                throw new FileWriteException(\sprintf('Could not atomically replace %s', $path));
            }
            $temporary = '';
        } finally {
            if ('' !== $temporary && file_exists($temporary)) {
                @unlink($temporary);
            }
        }
    }

    private function assertNotSymbolicLink(string $path): void
    {
        clearstatcache(true, $path);
        if (is_link($path)) {
            throw new FileWriteException(\sprintf('Refusing to replace symbolic link: %s', $path));
        }
    }
}
