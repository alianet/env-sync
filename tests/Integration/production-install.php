<?php

declare(strict_types=1);

$packageDirectory = realpath(__DIR__.'/../..');
if (false === $packageDirectory) {
    throw new RuntimeException('Cannot locate the package directory.');
}

$fixtureDirectory = sys_get_temp_dir().'/env-sync-consumer-'.bin2hex(random_bytes(8));
if (!mkdir($fixtureDirectory)) {
    throw new RuntimeException('Cannot create the temporary consumer application.');
}

/** @param list<string> $arguments */
$runComposer = static function (array $arguments) use ($fixtureDirectory): string {
    $composer = getenv('COMPOSER_BINARY') ?: 'composer';
    /** @var list<string> $command */
    $command = array_merge([$composer], $arguments);
    $pipes = [];
    $process = proc_open($command, [1 => ['pipe', 'w'], 2 => ['pipe', 'w']], $pipes, $fixtureDirectory);
    if (!is_resource($process)) {
        throw new RuntimeException('Cannot start Composer.');
    }
    $stdout = stream_get_contents($pipes[1]);
    $stderr = stream_get_contents($pipes[2]);
    fclose($pipes[1]);
    fclose($pipes[2]);
    $status = proc_close($process);
    if (0 !== $status) {
        throw new RuntimeException(sprintf("Composer failed with code %d.\n%s%s", $status, $stdout, $stderr));
    }

    return $stdout.$stderr;
};

/** @param list<string> $command */
$runCommand = static function (array $command) use ($fixtureDirectory): string {
    /** @var list<string> $command */
    $pipes = [];
    $process = proc_open($command, [1 => ['pipe', 'w'], 2 => ['pipe', 'w']], $pipes, $fixtureDirectory);
    if (!is_resource($process)) {
        throw new RuntimeException('Cannot start the production CLI.');
    }
    $stdout = stream_get_contents($pipes[1]);
    $stderr = stream_get_contents($pipes[2]);
    fclose($pipes[1]);
    fclose($pipes[2]);
    $status = proc_close($process);
    if (0 !== $status) {
        throw new RuntimeException(sprintf('Production CLI failed with code %d.', $status));
    }

    return $stdout.$stderr;
};

$removeDirectory = static function (string $directory) use (&$removeDirectory): void {
    foreach (new FilesystemIterator($directory) as $file) {
        if (!$file instanceof SplFileInfo) {
            throw new RuntimeException('Unexpected fixture entry.');
        }
        if ($file->isDir() && !$file->isLink()) {
            $removeDirectory($file->getPathname());
        } else {
            unlink($file->getPathname());
        }
    }
    rmdir($directory);
};

try {
    $consumerComposer = [
        'name' => 'alianet/env-sync-production-fixture',
        'type' => 'project',
        'repositories' => [[
            'type' => 'path',
            'url' => $packageDirectory,
            'options' => ['symlink' => false],
        ]],
        'require' => ['alianet/env-sync' => '@dev'],
        'minimum-stability' => 'dev',
        'prefer-stable' => true,
        'scripts' => [
            'env:update' => '@php vendor/bin/env-sync update .env.example .env',
            'post-install-cmd' => ['@env:update'],
            'post-update-cmd' => ['@env:update'],
        ],
    ];
    file_put_contents(
        $fixtureDirectory.'/composer.json',
        json_encode($consumerComposer, \JSON_PRETTY_PRINT | \JSON_UNESCAPED_SLASHES | \JSON_THROW_ON_ERROR)."\n",
    );
    file_put_contents($fixtureDirectory.'/.env.example', "APP_MODE=safe-fixture-default\nCACHE_URL=fixture-cache-default\n");

    $firstOutput = $runComposer(['install', '--no-dev', '--no-interaction', '--prefer-dist']);
    $targetPath = $fixtureDirectory.'/.env';
    if (!is_file($targetPath)) {
        throw new RuntimeException('The first production install did not create .env.');
    }
    if (!is_file($fixtureDirectory.'/vendor/bin/env-sync')) {
        throw new RuntimeException('Composer did not expose vendor/bin/env-sync.');
    }
    $schemaPath = $fixtureDirectory.'/vendor/alianet/env-sync/env-sync.schema.json';
    $schemaContents = file_get_contents($schemaPath);
    if (false === $schemaContents) {
        throw new RuntimeException('Composer did not install the configuration schema.');
    }
    json_decode($schemaContents, false, flags: \JSON_THROW_ON_ERROR);
    if (file_exists($fixtureDirectory.'/vendor/bin/phpunit')) {
        throw new RuntimeException('A development-only PHPUnit binary was installed.');
    }
    $cliOutput = $runCommand([$fixtureDirectory.'/vendor/bin/env-sync', '--version']);
    if (!str_contains($cliOutput, 'env-sync')) {
        throw new RuntimeException('The production CLI did not report its version.');
    }

    file_put_contents($targetPath, "APP_MODE=deployment-secret-value\nCACHE_URL=kept-existing-value\nEXTRA=kept-extra-value\n");
    file_put_contents($fixtureDirectory.'/.env.example', "APP_MODE=safe-fixture-default\nCACHE_URL=fixture-cache-default\nNEW_KEY=new-fixture-default\n");
    $updateOutput = $runComposer(['update', '--no-dev', '--no-interaction']);
    $afterUpdate = file_get_contents($targetPath);
    if (false === $afterUpdate
        || !str_contains($afterUpdate, 'APP_MODE=deployment-secret-value')
        || !str_contains($afterUpdate, 'CACHE_URL=kept-existing-value')
        || !str_contains($afterUpdate, 'EXTRA=kept-extra-value')
        || !str_contains($afterUpdate, 'NEW_KEY=new-fixture-default')) {
        throw new RuntimeException('The production update did not preserve and append the expected entries.');
    }

    $secondInstallOutput = $runComposer(['install', '--no-dev', '--no-interaction', '--prefer-dist']);
    if ($afterUpdate !== file_get_contents($targetPath)) {
        throw new RuntimeException('A repeated production install was not idempotent.');
    }
    $combinedOutput = $firstOutput.$cliOutput.$updateOutput.$secondInstallOutput;
    foreach (['deployment-secret-value', 'kept-existing-value', 'kept-extra-value', 'new-fixture-default'] as $value) {
        if (str_contains($combinedOutput, $value)) {
            throw new RuntimeException('Composer Script output disclosed a dotenv value.');
        }
    }

    echo "Production path-repository install passed with --no-dev; Composer hooks are idempotent.\n";
} finally {
    $removeDirectory($fixtureDirectory);
}
