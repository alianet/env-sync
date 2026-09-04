<?php

declare(strict_types=1);

namespace Alianet\EnvSync\Application;

final class ConfigurationLoader
{
    public const DEFAULT_PATH = '.env-sync.json';
    private const ALLOWED_FIELDS = ['$schema', 'template', 'target', 'allowed_extra_keys', 'allowed_extra_patterns'];

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
            throw new \RuntimeException(\sprintf('Cannot read configuration file: %s', $path));
        }

        $contents = file_get_contents($path);
        if (false === $contents) {
            throw new \RuntimeException(\sprintf('Cannot read configuration file: %s', $path));
        }

        try {
            $decoded = json_decode($contents, false, flags: \JSON_THROW_ON_ERROR);
        } catch (\JsonException $exception) {
            throw new \InvalidArgumentException(\sprintf('Invalid JSON in configuration file %s: %s', $path, $exception->getMessage()));
        }
        if (!$decoded instanceof \stdClass) {
            throw new \InvalidArgumentException(\sprintf('Configuration file %s must contain a JSON object.', $path));
        }

        /** @var array<string, mixed> $values */
        $values = get_object_vars($decoded);
        $unknownFields = array_diff(array_keys($values), self::ALLOWED_FIELDS);
        if ([] !== $unknownFields) {
            throw new \InvalidArgumentException(\sprintf('Unknown configuration field in %s: %s', $path, implode(', ', $unknownFields)));
        }
        if (\array_key_exists('$schema', $values) && (!\is_string($values['$schema']) || '' === $values['$schema'])) {
            throw new \InvalidArgumentException(\sprintf('Configuration field "$schema" in %s must be a non-empty string.', $path));
        }

        $directory = \dirname($path);

        return new SyncConfiguration(
            $this->pathValue($values, 'template', $path, $directory),
            $this->pathValue($values, 'target', $path, $directory),
            $this->allowedExtraKeys($values, $path),
            $this->allowedExtraPatterns($values, $path),
        );
    }

    /** @param array<string, mixed> $values */
    private function pathValue(array $values, string $field, string $configurationPath, string $directory): ?string
    {
        if (!\array_key_exists($field, $values)) {
            return null;
        }
        if (!\is_string($values[$field]) || '' === $values[$field]) {
            throw new \InvalidArgumentException(\sprintf('Configuration field "%s" in %s must be a non-empty string.', $field, $configurationPath));
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
        if (!\array_key_exists('allowed_extra_keys', $values)) {
            return [];
        }
        if (!\is_array($values['allowed_extra_keys']) || !array_is_list($values['allowed_extra_keys'])) {
            throw new \InvalidArgumentException(\sprintf('Configuration field "allowed_extra_keys" in %s must be a JSON array.', $configurationPath));
        }

        $keys = [];
        foreach ($values['allowed_extra_keys'] as $key) {
            if (!\is_string($key) || 1 !== preg_match('/^[A-Za-z_][A-Za-z0-9_.-]*$/', $key)) {
                throw new \InvalidArgumentException(\sprintf('Configuration field "allowed_extra_keys" in %s contains an invalid key.', $configurationPath));
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
        if (!\array_key_exists('allowed_extra_patterns', $values)) {
            return [];
        }
        if (!\is_array($values['allowed_extra_patterns']) || !array_is_list($values['allowed_extra_patterns'])) {
            throw new \InvalidArgumentException(\sprintf('Configuration field "allowed_extra_patterns" in %s must be a JSON array.', $configurationPath));
        }

        $patterns = [];
        foreach ($values['allowed_extra_patterns'] as $pattern) {
            if (!\is_string($pattern) || 1 !== preg_match('/^[A-Za-z0-9_.?*-]+$/', $pattern)) {
                throw new \InvalidArgumentException(\sprintf('Configuration field "allowed_extra_patterns" in %s contains an invalid pattern.', $configurationPath));
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

        return $directory.\DIRECTORY_SEPARATOR.$path;
    }

    private function isAbsolutePath(string $path): bool
    {
        return str_starts_with($path, '/')
            || str_starts_with($path, '\\')
            || 1 === preg_match('/^[A-Za-z]:[\\\\\/]/', $path);
    }
}
