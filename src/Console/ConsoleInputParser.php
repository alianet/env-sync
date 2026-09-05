<?php

declare(strict_types=1);

namespace Alianet\EnvSync\Console;

use Alianet\EnvSync\Exception\InvalidConsoleInputException;

final class ConsoleInputParser
{
    /** @param list<string> $arguments */
    public function parse(array $arguments): ConsoleInput
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
                    throw new InvalidConsoleInputException('Unsupported format. Available formats: text, json.');
                }

                continue;
            }
            if ($options && str_starts_with($argument, '--config=')) {
                $configuration = substr($argument, \strlen('--config='));
                if ('' === $configuration) {
                    throw new InvalidConsoleInputException('The --config option requires a path.');
                }

                continue;
            }
            if ($options && str_starts_with($argument, '-')) {
                throw new InvalidConsoleInputException('Unknown option. Run env-sync --help for usage.');
            }
            $positionals[] = $argument;
        }

        if ($version) {
            return new ConsoleInput(
                command: null,
                template: null,
                target: null,
                dryRun: false,
                verbose: false,
                help: false,
                version: true,
                format: 'text',
                configuration: null,
            );
        }
        if ([] === $positionals) {
            if ($dryRun) {
                throw new InvalidConsoleInputException('The --dry-run option requires the update command.');
            }
            if ('text' !== $format) {
                throw new InvalidConsoleInputException('The --format option requires the diff command.');
            }
            if (null !== $configuration) {
                throw new InvalidConsoleInputException('The --config option requires a command.');
            }

            return new ConsoleInput(
                command: null,
                template: null,
                target: null,
                dryRun: false,
                verbose: $verbose,
                help: $help,
                version: false,
                format: $format,
                configuration: null,
            );
        }

        $command = array_shift($positionals);
        if (!\in_array($command, ['diff', 'update', 'validate-config'], true)) {
            throw new InvalidConsoleInputException('Unknown command. Run env-sync --help for usage.');
        }
        if ('update' !== $command && $dryRun) {
            throw new InvalidConsoleInputException('The --dry-run option is only available for update.');
        }
        if ('diff' !== $command && 'text' !== $format) {
            throw new InvalidConsoleInputException('The --format option is only available for diff.');
        }
        if ('validate-config' === $command && [] !== $positionals) {
            throw new InvalidConsoleInputException('The validate-config command does not accept file arguments.');
        }
        if (\count($positionals) > 2) {
            throw new InvalidConsoleInputException('Too many arguments. Run env-sync --help for usage.');
        }

        return new ConsoleInput(
            command: $command,
            template: $positionals[0] ?? null,
            target: $positionals[1] ?? null,
            dryRun: $dryRun,
            verbose: $verbose,
            help: $help,
            version: false,
            format: $format,
            configuration: $configuration,
        );
    }
}
