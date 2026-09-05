<?php

declare(strict_types=1);

namespace Alianet\EnvSync\Tests\Application;

use Alianet\EnvSync\Application\ConfigurationLoader;
use Alianet\EnvSync\Exception\ConfigurationFileReadException;
use Alianet\EnvSync\Exception\InvalidConfigurationException;
use PHPUnit\Framework\TestCase;

final class ConfigurationLoaderTest extends TestCase
{
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
