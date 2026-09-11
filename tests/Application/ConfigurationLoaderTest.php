<?php

declare(strict_types=1);

namespace Alianet\EnvSync\Tests\Application;

use Alianet\EnvSync\Application\ConfigurationLoader;
use Alianet\EnvSync\Exception\ConfigurationFileReadException;
use Alianet\EnvSync\Exception\InvalidConfigurationException;
use PHPUnit\Framework\TestCase;

final class ConfigurationLoaderTest extends TestCase
{
    public function testLoadsConditionalRequirements(): void
    {
        $path = tempnam(sys_get_temp_dir(), 'env-sync-configuration-test-');
        self::assertIsString($path);

        try {
            self::assertNotFalse(file_put_contents($path, json_encode([
                'conditional_requirements' => [[
                    'key' => 'ACCOUNT_TYPE',
                    'equals' => 'company',
                    'required_keys' => ['CLIENT_ID', 'CLIENT_SECRET'],
                    'required_changed_keys' => ['CLIENT_SECRET'],
                ]],
            ], \JSON_THROW_ON_ERROR)));

            $configuration = (new ConfigurationLoader())->load($path);

            self::assertCount(1, $configuration->conditionalRequirements);
            self::assertSame('ACCOUNT_TYPE', $configuration->conditionalRequirements[0]->key);
            self::assertSame('company', $configuration->conditionalRequirements[0]->equals);
            self::assertSame(['CLIENT_ID', 'CLIENT_SECRET'], $configuration->conditionalRequirements[0]->requiredKeys);
            self::assertSame(['CLIENT_SECRET'], $configuration->conditionalRequirements[0]->requiredChangedKeys);
        } finally {
            unlink($path);
        }
    }

    public function testRejectsInvalidConditionalRequirementWithoutDisplayingItsValue(): void
    {
        $path = tempnam(sys_get_temp_dir(), 'env-sync-configuration-test-');
        self::assertIsString($path);

        try {
            self::assertNotFalse(file_put_contents($path, '{"conditional_requirements":[{"key":"ACCOUNT_TYPE","equals":42}]}'));

            try {
                (new ConfigurationLoader())->load($path);
                self::fail('Expected invalid conditional requirement to be rejected.');
            } catch (InvalidConfigurationException $exception) {
                self::assertStringContainsString('ACCOUNT_TYPE', $exception->getMessage());
                self::assertStringNotContainsString('42', $exception->getMessage());
            }
        } finally {
            unlink($path);
        }
    }

    public function testThrowsDedicatedExceptionWhenConfigurationCannotBeRead(): void
    {
        $this->expectException(ConfigurationFileReadException::class);

        (new ConfigurationLoader())->load('missing.json');
    }

    public function testWrapsJsonErrorInDedicatedException(): void
    {
        $path = tempnam(sys_get_temp_dir(), 'env-sync-configuration-test-');
        self::assertIsString($path);

        try {
            self::assertNotFalse(file_put_contents($path, '{'));

            try {
                (new ConfigurationLoader())->load($path);
                self::fail('Expected invalid JSON to be rejected.');
            } catch (InvalidConfigurationException $exception) {
                self::assertInstanceOf(\JsonException::class, $exception->getPrevious());
            }
        } finally {
            unlink($path);
        }
    }
}
