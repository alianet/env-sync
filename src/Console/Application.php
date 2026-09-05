<?php

declare(strict_types=1);

namespace Alianet\EnvSync\Console;

use Alianet\EnvSync\Application\ConfigurationLoader;
use Alianet\EnvSync\Application\EnvSyncService;
use Alianet\EnvSync\Application\SyncConfiguration;
use Alianet\EnvSync\Diff\ComparisonRules;
use Alianet\EnvSync\Diff\DiffResult;
use Alianet\EnvSync\Exception\UserFacingException;

final readonly class Application
{
    public function __construct(
        private EnvSyncService $service,
        private PathResolver $paths,
        private ConfigurationLoader $configuration,
        private ConsoleInputParser $inputParser,
    ) {
    }

    /**
     * @param list<string>            $arguments
     * @param callable(string): mixed $stdout
     * @param callable(string): mixed $stderr
     */
    public function run(array $arguments, callable $stdout, callable $stderr): int
    {
        try {
            $input = $this->inputParser->parse($arguments);

            if ($input->version) {
                $stdout('env-sync '.$this->version()."\n");

                return 0;
            }
            if (null === $input->command) {
                $stdout($this->help());

                return 0;
            }
            if ($input->help) {
                $stdout($this->commandHelp($input->command));

                return 0;
            }
            if ('validate-config' === $input->command) {
                $path = $input->configuration ?? ConfigurationLoader::DEFAULT_PATH;
                $this->configuration->loadRequired($input->configuration);
                $stdout(\sprintf("Configuration %s is valid.\n", $path));

                return 0;
            }

            $configuration = $this->configuration->load($input->configuration);
            [$template, $target] = $this->paths->resolve($input->template, $input->target, $configuration);

            return 'diff' === $input->command
                ? $this->diff($template, $target, $configuration, $input->format, $stdout)
                : $this->update($template, $target, $input->dryRun, $input->verbose, $stdout);
        } catch (UserFacingException $exception) {
            $stderr($exception->getMessage()."\n");

            return 2;
        }
    }

    /** @param callable(string): mixed $output */
    private function diff(
        string $template,
        string $target,
        SyncConfiguration $configuration,
        string $format,
        callable $output,
    ): int {
        $result = $this->service->diff($template, $target, new ComparisonRules(
            $configuration->allowedExtraKeys,
            $configuration->allowedExtraPatterns,
        ));
        if ('json' === $format) {
            $this->renderJsonDiff($output, $result, $template, $target);
        } else {
            $this->renderDiff($output, $result, $template, $target);
        }

        return $result->hasDifferences() ? 1 : 0;
    }

    /** @param callable(string): mixed $output */
    private function update(string $template, string $target, bool $dryRun, bool $verbose, callable $output): int
    {
        $result = $this->service->planUpdate($template, $target);
        if ([] !== $result->addedKeys && !$dryRun) {
            $this->service->write($target, $result);
        }
        if ([] === $result->addedKeys) {
            $output(\sprintf("%s is already up to date.\n", $target));

            return 0;
        }

        $count = \count($result->addedKeys);
        $noun = 1 === $count ? 'variable' : 'variables';
        $action = $dryRun ? 'would be updated' : 'updated';
        $output(\sprintf("%s %s: %d %s added.\n", $target, $action, $count, $noun));
        if ($verbose) {
            foreach ($result->addedKeys as $key) {
                $output(\sprintf("  + %s\n", $key));
            }
        }

        return 0;
    }

    /** @param callable(string): mixed $output */
    private function renderDiff(callable $output, DiffResult $result, string $template, string $target): void
    {
        /** @var array<string, array{string, list<string>}> $sections */
        $sections = [
            \sprintf('Missing in %s:', $target) => ['+', $result->missing],
            \sprintf('Only in %s:', $target) => ['?', $result->extra],
            \sprintf('Duplicate keys in %s:', $template) => ['!', $result->templateDuplicates],
            \sprintf('Duplicate keys in %s:', $target) => ['!', $result->targetDuplicates],
        ];
        foreach ($sections as $heading => [$marker, $keys]) {
            if ([] === $keys) {
                continue;
            }
            $output($heading."\n");
            foreach ($keys as $key) {
                $output(\sprintf("  %s %s\n", $marker, $key));
            }
        }
        if (!$result->hasDifferences()) {
            $output("Files contain the same keys.\n");
        }
    }

    /** @param callable(string): mixed $output */
    private function renderJsonDiff(callable $output, DiffResult $result, string $template, string $target): void
    {
        $output(json_encode([
            'template' => $template,
            'target' => $target,
            'has_differences' => $result->hasDifferences(),
            'missing' => $result->missing,
            'additional' => $result->extra,
            'duplicate_keys' => [
                'template' => $result->templateDuplicates,
                'target' => $result->targetDuplicates,
            ],
        ], \JSON_PRETTY_PRINT | \JSON_UNESCAPED_SLASHES | \JSON_THROW_ON_ERROR)."\n");
    }

    private function help(): string
    {
        return \sprintf(<<<'HELP'
env-sync %s

Usage:
  env-sync diff [--format=json] [--config=path] [template] [target]
  env-sync update [--dry-run] [-v|--verbose] [--config=path] [template] [target]
  env-sync validate-config [--config=path]

Options:
  --config=path   Read project rules from this JSON file
  --dry-run       Show what update would add without writing
  --format=json   Print diff results as JSON
  -v, --verbose   List added variable names
  -h, --help      Show help
  -V, --version   Show version

HELP, $this->version());
    }

    private function version(): string
    {
        $path = \dirname(__DIR__, 2).'/VERSION';
        if (!is_readable($path)) {
            throw new \RuntimeException('Cannot read application version.');
        }

        $version = file_get_contents($path);
        if (false === $version || '' === trim($version)) {
            throw new \RuntimeException('Cannot read application version.');
        }

        return trim($version);
    }

    private function commandHelp(string $command): string
    {
        if ('diff' === $command) {
            return "Usage: env-sync diff [--format=json] [--config=path] [template] [target]\n\nCompare dotenv keys without displaying their values.\n";
        }
        if ('validate-config' === $command) {
            return "Usage: env-sync validate-config [--config=path]\n\nValidate configuration without reading dotenv files.\n";
        }

        return "Usage: env-sync update [--dry-run] [-v|--verbose] [--config=path] [template] [target]\n\nAppend missing dotenv entries without replacing existing values.\n";
    }
}
