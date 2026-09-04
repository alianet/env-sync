<?php

declare(strict_types=1);

namespace Alianet\EnvSync\Console;

use Alianet\EnvSync\Application\ConfigurationLoader;
use Alianet\EnvSync\Application\EnvSyncService;
use Alianet\EnvSync\Application\SyncConfiguration;
use Alianet\EnvSync\Diff\ComparisonRules;
use Alianet\EnvSync\Diff\DiffResult;

final readonly class Application
{
    private const VERSION = '0.1.0';

    public function __construct(
        private EnvSyncService $service,
        private PathResolver $paths,
        private ConfigurationLoader $configuration,
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
            $input = $this->parse($arguments);

            if ($input->version) {
                $stdout('env-sync '.self::VERSION."\n");

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
        } catch (\Throwable $exception) {
            $stderr($exception->getMessage()."\n");

            return 2;
        }
    }

    /** @param list<string> $arguments */
    private function parse(array $arguments): ConsoleInput
    {
        $positionals = [];
        $verbose = false;
        $dryRun = false;
        $help = false;
        $version = false;
        $format = 'text';
        $configuration = null;
        $options = true;

        foreach ($arguments as $argument) {
            if ($options && '--' === $argument) {
                $options = false;
                continue;
            }
            if ($options && \in_array($argument, ['-v', '--verbose'], true)) {
                $verbose = true;
                continue;
            }
            if ($options && '--dry-run' === $argument) {
                $dryRun = true;
                continue;
            }
            if ($options && \in_array($argument, ['-h', '--help'], true)) {
                $help = true;
                continue;
            }
            if ($options && \in_array($argument, ['-V', '--version'], true)) {
                $version = true;
                continue;
            }
            if ($options && str_starts_with($argument, '--format=')) {
                $format = substr($argument, \strlen('--format='));
                if (!\in_array($format, ['text', 'json'], true)) {
                    throw new \InvalidArgumentException('Unsupported format. Available formats: text, json.');
                }

                continue;
            }
            if ($options && str_starts_with($argument, '--config=')) {
                $configuration = substr($argument, \strlen('--config='));
                if ('' === $configuration) {
                    throw new \InvalidArgumentException('The --config option requires a path.');
                }

                continue;
            }
            if ($options && str_starts_with($argument, '-')) {
                throw new \InvalidArgumentException('Unknown option. Run env-sync --help for usage.');
            }
            $positionals[] = $argument;
        }

        if ($version) {
            return new ConsoleInput(null, null, null, false, false, false, true, 'text', null);
        }
        if ([] === $positionals) {
            if ($dryRun) {
                throw new \InvalidArgumentException('The --dry-run option requires the update command.');
            }
            if ('text' !== $format) {
                throw new \InvalidArgumentException('The --format option requires the diff command.');
            }
            if (null !== $configuration) {
                throw new \InvalidArgumentException('The --config option requires a command.');
            }

            return new ConsoleInput(null, null, null, false, $verbose, $help, false, $format, null);
        }

        $command = array_shift($positionals);
        if (!\in_array($command, ['diff', 'update', 'validate-config'], true)) {
            throw new \InvalidArgumentException('Unknown command. Run env-sync --help for usage.');
        }
        if ('update' !== $command && $dryRun) {
            throw new \InvalidArgumentException('The --dry-run option is only available for update.');
        }
        if ('diff' !== $command && 'text' !== $format) {
            throw new \InvalidArgumentException('The --format option is only available for diff.');
        }
        if ('validate-config' === $command && [] !== $positionals) {
            throw new \InvalidArgumentException('The validate-config command does not accept file arguments.');
        }
        if (\count($positionals) > 2) {
            throw new \InvalidArgumentException('Too many arguments. Run env-sync --help for usage.');
        }

        return new ConsoleInput(
            $command,
            $positionals[0] ?? null,
            $positionals[1] ?? null,
            $dryRun,
            $verbose,
            $help,
            false,
            $format,
            $configuration,
        );
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
        return <<<'HELP'
env-sync 0.1.0

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

HELP;
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
