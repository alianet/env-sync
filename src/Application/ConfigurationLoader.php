<?php

declare(strict_types=1);

namespace Alianet\EnvSync\Application;

use Alianet\EnvSync\Exception\ConfigurationFileReadException;
use Alianet\EnvSync\Exception\InvalidConfigurationException;

final class ConfigurationLoader
{
    public const DEFAULT_PATH = '.env-sync.json';
    private const FIELD_SCHEMA = '$schema';
    private const FIELD_TEMPLATE = 'template';
    private const FIELD_TARGET = 'target';
    private const FIELD_ALLOWED_EXTRA_KEYS = 'allowed_extra_keys';
    private const FIELD_ALLOWED_EXTRA_PATTERNS = 'allowed_extra_patterns';
    private const FIELD_REQUIRED_CHANGED_KEYS = 'required_changed_keys';
    private const ALLOWED_FIELDS = [
        self::FIELD_SCHEMA,
        self::FIELD_TEMPLATE,
        self::FIELD_TARGET,
        self::FIELD_ALLOWED_EXTRA_KEYS,
        self::FIELD_ALLOWED_EXTRA_PATTERNS,
        self::FIELD_REQUIRED_CHANGED_KEYS,
    ];

    public function loadRequired(?string $path): SyncConfiguration
    {
        return $this->load($path ?? self::DEFAULT_PATH);
    }

    public function load(?string $path): SyncConfiguration
    {
        $explicit = null !== $path;
        $path ??= self::DEFAULT_PATH;
        if (!$explicit && !file_exists($path)) {
            return new SyncConfiguration();
        }
        if (!is_file($path) || !is_readable($path)) {
            throw new ConfigurationFileReadException(\sprintf('Cannot read configuration file: %s', $path));
        }

        $contents = file_get_contents($path);
        if (false === $contents) {
            throw new ConfigurationFileReadException(\sprintf('Cannot read configuration file: %s', $path));
        }

        try {
            $decoded = json_decode($contents, false, flags: \JSON_THROW_ON_ERROR);
        } catch (\JsonException $exception) {
            throw new InvalidConfigurationException(\sprintf('Invalid JSON in configuration file %s: %s', $path, $exception->getMessage()), previous: $exception);
        }
        if (!$decoded instanceof \stdClass) {
            throw new InvalidConfigurationException(\sprintf('Configuration file %s must contain a JSON object.', $path));
        }

        /** @var array<string, mixed> $values */
        $values = get_object_vars($decoded);
        $unknownFields = array_diff(array_keys($values), self::ALLOWED_FIELDS);
        if ([] !== $unknownFields) {
            throw new InvalidConfigurationException(\sprintf('Unknown configuration field in %s: %s', $path, implode(', ', $unknownFields)));
        }
        if (\array_key_exists(self::FIELD_SCHEMA, $values) && (!\is_string($values[self::FIELD_SCHEMA]) || '' === $values[self::FIELD_SCHEMA])) {
            throw new InvalidConfigurationException(\sprintf('Configuration field "%s" in %s must be a non-empty string.', self::FIELD_SCHEMA, $path));
        }

        $directory = \dirname($path);

        return new SyncConfiguration(
            $this->pathValue($values, self::FIELD_TEMPLATE, $path, $directory),
            $this->pathValue($values, self::FIELD_TARGET, $path, $directory),
            $this->allowedExtraKeys($values, $path),
            $this->allowedExtraPatterns($values, $path),
            $this->keys($values, self::FIELD_REQUIRED_CHANGED_KEYS, $path),
        );
    }

    /** @param array<string, mixed> $values */
    private function pathValue(array $values, string $field, string $configurationPath, string $directory): ?string
    {
        if (!\array_key_exists($field, $values)) {
            return null;
        }
        if (!\is_string($values[$field]) || '' === $values[$field]) {
            throw new InvalidConfigurationException(\sprintf('Configuration field "%s" in %s must be a non-empty string.', $field, $configurationPath));
        }

        return $this->resolvePath($values[$field], $directory);
    }

    /**
     * @param array<string, mixed> $values
     *
     * @return list<string>
     */
    private function allowedExtraKeys(array $values, string $configurationPath): array
    {
        return $this->keys($values, self::FIELD_ALLOWED_EXTRA_KEYS, $configurationPath);
    }

    /**
     * @param array<string, mixed> $values
     *
     * @return list<string>
     */
    private function keys(array $values, string $field, string $configurationPath): array
    {
        if (!\array_key_exists($field, $values)) {
            return [];
        }
        if (!\is_array($values[$field]) || !array_is_list($values[$field])) {
            throw new InvalidConfigurationException(\sprintf('Configuration field "%s" in %s must be a JSON array.', $field, $configurationPath));
        }

        $keys = [];
        foreach ($values[$field] as $key) {
            if (!\is_string($key) || 1 !== preg_match('/^[A-Za-z_][A-Za-z0-9_.-]*$/', $key)) {
                throw new InvalidConfigurationException(\sprintf('Configuration field "%s" in %s contains an invalid key.', $field, $configurationPath));
            }
            $keys[$key] = true;
        }

        return array_keys($keys);
    }

    /**
     * @param array<string, mixed> $values
     *
     * @return list<string>
     */
    private function allowedExtraPatterns(array $values, string $configurationPath): array
    {
        if (!\array_key_exists(self::FIELD_ALLOWED_EXTRA_PATTERNS, $values)) {
            return [];
        }
        if (!\is_array($values[self::FIELD_ALLOWED_EXTRA_PATTERNS]) || !array_is_list($values[self::FIELD_ALLOWED_EXTRA_PATTERNS])) {
            throw new InvalidConfigurationException(\sprintf('Configuration field "%s" in %s must be a JSON array.', self::FIELD_ALLOWED_EXTRA_PATTERNS, $configurationPath));
        }

        $patterns = [];
        foreach ($values[self::FIELD_ALLOWED_EXTRA_PATTERNS] as $pattern) {
            if (!\is_string($pattern) || 1 !== preg_match('/^[A-Za-z0-9_.?*-]+$/', $pattern)) {
                throw new InvalidConfigurationException(\sprintf('Configuration field "%s" in %s contains an invalid pattern.', self::FIELD_ALLOWED_EXTRA_PATTERNS, $configurationPath));
            }
            $patterns[$pattern] = true;
        }

        return array_keys($patterns);
    }

    private function resolvePath(string $path, string $directory): string
    {
        if ('.' === $directory || $this->isAbsolutePath($path)) {
            return $path;
        }

        return $directory . \DIRECTORY_SEPARATOR . $path;
    }

    private function isAbsolutePath(string $path): bool
    {
        return str_starts_with($path, '/')
            || str_starts_with($path, '\\')
            || 1 === preg_match('/^[A-Za-z]:[\\\\\/]/', $path);
    }
}
