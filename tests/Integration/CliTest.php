<?php

declare(strict_types=1);

namespace Alianet\EnvSync\Tests\Integration;

use Alianet\EnvSync\Application\EnvSyncService;
use Alianet\EnvSync\Console\ApplicationFactory;
use Alianet\EnvSync\Diff\Comparator;
use Alianet\EnvSync\Document\Parser;
use Alianet\EnvSync\Document\Updater;
use Alianet\EnvSync\Filesystem\FileWriter;
use PHPUnit\Framework\TestCase;

final class CliTest extends TestCase
{
    private string $directory;
    private string $originalDirectory;

    protected function setUp(): void
    {
        $this->originalDirectory = getcwd() ?: throw new \RuntimeException('Cannot determine working directory.');
        $this->directory = sys_get_temp_dir().'/env-sync-test-'.bin2hex(random_bytes(8));
        self::assertTrue(mkdir($this->directory));
        self::assertTrue(chdir($this->directory));
    }

    protected function tearDown(): void
    {
        chdir($this->originalDirectory);
        $this->removeDirectory($this->directory);
    }

    public function testDiffOutputContainsKeysButNeverValues(): void
    {
        file_put_contents('.env.example', "SHARED=template-public\nMISSING=default-secret\n");
        file_put_contents('.env', "SHARED=actual-secret\nEXTRA=private-token\n");

        [$status, $display] = $this->runCli(['command' => 'diff']);

        self::assertSame(1, $status);
        self::assertStringContainsString('+ MISSING', $display);
        self::assertStringContainsString('? EXTRA', $display);
        self::assertStringNotContainsString('default-secret', $display);
        self::assertStringNotContainsString('private-token', $display);
    }

    public function testDiffCanRenderJsonWithoutValues(): void
    {
        file_put_contents('.env.example', "SHARED=template-public\nMISSING=default-secret\nDUPLICATE=one\nDUPLICATE=two\n");
        file_put_contents('.env', "SHARED=actual-secret\nADDITIONAL=private-token\n");

        [$status, $display] = $this->runCli(['command' => 'diff', '--format=json' => true]);

        self::assertSame(1, $status);
        self::assertSame([
            'template' => '.env.example',
            'target' => '.env',
            'has_differences' => true,
            'missing' => ['MISSING', 'DUPLICATE'],
            'additional' => ['ADDITIONAL'],
            'duplicate_keys' => [
                'template' => ['DUPLICATE'],
                'target' => [],
            ],
        ], json_decode($display, true, flags: \JSON_THROW_ON_ERROR));
        self::assertStringNotContainsString('default-secret', $display);
        self::assertStringNotContainsString('private-token', $display);
    }

    public function testJsonDiffWithoutDifferencesReturnsZero(): void
    {
        file_put_contents('.env.example', "A=template\n");
        file_put_contents('.env', "A=secret\n");

        [$status, $display] = $this->runCli(['command' => 'diff', '--format=json' => true]);

        self::assertSame(0, $status);
        self::assertSame([
            'template' => '.env.example',
            'target' => '.env',
            'has_differences' => false,
            'missing' => [],
            'additional' => [],
            'duplicate_keys' => [
                'template' => [],
                'target' => [],
            ],
        ], json_decode($display, true, flags: \JSON_THROW_ON_ERROR));
    }

    public function testUpdateCreatesMissingTargetAndAddsTemplateDefaults(): void
    {
        file_put_contents('.env.example', "FIRST=one\nSECOND=\"two # value\"\n");

        [$status, $display] = $this->runCli(['command' => 'update']);

        self::assertSame(0, $status);
        self::assertSame("# Added by env-sync\nFIRST=one\nSECOND=\"two # value\"\n", file_get_contents('.env'));
        self::assertSame(".env updated: 2 variables added.\n", $display);
        self::assertStringNotContainsString('FIRST', $display);
        self::assertStringNotContainsString('two # value', $display);
    }

    public function testDryRunDoesNotCreateOrChangeTarget(): void
    {
        file_put_contents('.env.example', "A=default\nB=sensitive-default\n");
        file_put_contents('.env', "A=local\n");

        [$status, $display] = $this->runCli(['command' => 'update', '--dry-run' => true]);

        self::assertSame(0, $status);
        self::assertSame("A=local\n", file_get_contents('.env'));
        self::assertSame(".env would be updated: 1 variable added.\n", $display);
        self::assertStringNotContainsString('B', $display);
        self::assertStringNotContainsString('sensitive-default', $display);
    }

    public function testVerboseUpdateListsKeysButNotValues(): void
    {
        file_put_contents('.env.example', "A=sensitive-default\n");

        [$status, $display] = $this->runCli(['command' => 'update', '--dry-run' => true, '--verbose' => true]);

        self::assertSame(0, $status);
        self::assertStringContainsString('+ A', $display);
        self::assertStringNotContainsString('sensitive-default', $display);
        self::assertFileDoesNotExist('.env');
    }

    public function testSecondUpdateIsIdempotentAndReturnsZero(): void
    {
        file_put_contents('.env.example', "A=default\nB=default\n");
        file_put_contents('.env', "A=existing-secret\n");

        self::assertSame(0, $this->runCli(['command' => 'update'])[0]);
        $afterFirstUpdate = file_get_contents('.env');
        [$status, $display] = $this->runCli(['command' => 'update']);

        self::assertSame(0, $status);
        self::assertSame($afterFirstUpdate, file_get_contents('.env'));
        self::assertSame(".env is already up to date.\n", $display);
        self::assertStringNotContainsString('existing-secret', $display);
    }

    public function testFallsBackToEnvDist(): void
    {
        file_put_contents('.env.dist', "A=1\n");

        [$status, $display] = $this->runCli(['command' => 'update']);

        self::assertSame(0, $status);
        self::assertSame("# Added by env-sync\nA=1\n", file_get_contents('.env'));
        self::assertSame(".env updated: 1 variable added.\n", $display);
    }

    public function testDefaultConfigurationSelectsPathsAndAllowsExtraKeys(): void
    {
        file_put_contents('.env.template', "SHARED=template\n");
        file_put_contents('.env.local', "SHARED=secret\nLOCAL_ONLY=private\nDEV_CACHE=private\n");
        file_put_contents('.env-sync.json', json_encode([
            'template' => '.env.template',
            'target' => '.env.local',
            'allowed_extra_keys' => ['LOCAL_ONLY'],
            'allowed_extra_patterns' => ['DEV_*'],
        ], \JSON_THROW_ON_ERROR));

        [$status, $display] = $this->runCli(['command' => 'diff']);

        self::assertSame(0, $status);
        self::assertSame("Files contain the same keys.\n", $display);
        self::assertStringNotContainsString('private', $display);
    }

    public function testExplicitConfigurationResolvesPathsRelativeToItsDirectory(): void
    {
        self::assertTrue(mkdir('config'));
        file_put_contents('config/template.env', "A=default\n");
        file_put_contents('config/env-sync.json', json_encode([
            'template' => 'template.env',
            'target' => 'local.env',
        ], \JSON_THROW_ON_ERROR));

        [$status, $display] = $this->runCli([
            'command' => 'update',
            'configuration' => 'config/env-sync.json',
        ]);

        self::assertSame(0, $status);
        self::assertFileExists('config/local.env');
        self::assertSame("config/local.env updated: 1 variable added.\n", $display);
    }

    public function testCommandLinePathsOverrideConfigurationPaths(): void
    {
        file_put_contents('.env-sync.json', json_encode([
            'template' => 'unused.template',
            'target' => 'unused.target',
        ], \JSON_THROW_ON_ERROR));
        file_put_contents('explicit.template', "A=default\n");
        file_put_contents('explicit.target', "A=secret\n");

        [$status, $display] = $this->runCli([
            'command' => 'diff',
            'template' => 'explicit.template',
            'target' => 'explicit.target',
        ]);

        self::assertSame(0, $status);
        self::assertSame("Files contain the same keys.\n", $display);
    }

    public function testInvalidConfigurationReturnsUsageError(): void
    {
        file_put_contents('.env-sync.json', '{"allowed_extra_keys":["VALID",42]}');

        [$status, $display] = $this->runCli(['command' => 'diff']);

        self::assertSame(2, $status);
        self::assertSame(
            "Configuration field \"allowed_extra_keys\" in .env-sync.json contains an invalid key.\n",
            $display,
        );
    }

    public function testInvalidAllowedExtraPatternReturnsUsageError(): void
    {
        file_put_contents('.env-sync.json', '{"allowed_extra_patterns":["LOCAL_[A-Z]+"]}');

        [$status, $display] = $this->runCli(['command' => 'diff']);

        self::assertSame(2, $status);
        self::assertSame(
            "Configuration field \"allowed_extra_patterns\" in .env-sync.json contains an invalid pattern.\n",
            $display,
        );
    }

    public function testMissingExplicitConfigurationReturnsReadableError(): void
    {
        [$status, $display] = $this->runCli([
            'command' => 'diff',
            'configuration' => 'config/missing.json',
        ]);

        self::assertSame(2, $status);
        self::assertSame("Cannot read configuration file: config/missing.json\n", $display);
    }

    public function testValidateConfigDoesNotRequireDotenvFiles(): void
    {
        file_put_contents('.env-sync.json', json_encode([
            '$schema' => './vendor/alianet/env-sync/env-sync.schema.json',
            'template' => 'missing.template',
            'target' => 'missing.target',
            'allowed_extra_patterns' => ['LOCAL_*'],
        ], \JSON_THROW_ON_ERROR));

        [$status, $display] = $this->runCli(['command' => 'validate-config']);

        self::assertSame(0, $status);
        self::assertSame("Configuration .env-sync.json is valid.\n", $display);
        self::assertFileDoesNotExist('missing.template');
        self::assertFileDoesNotExist('missing.target');
    }

    public function testValidateConfigRejectsInvalidSchemaReference(): void
    {
        file_put_contents('.env-sync.json', '{"$schema":""}');

        [$status, $display] = $this->runCli(['command' => 'validate-config']);

        self::assertSame(2, $status);
        self::assertSame(
            "Configuration field \"\$schema\" in .env-sync.json must be a non-empty string.\n",
            $display,
        );
    }

    public function testValidateConfigRequiresTheDefaultConfigurationFile(): void
    {
        [$status, $display] = $this->runCli(['command' => 'validate-config']);

        self::assertSame(2, $status);
        self::assertSame("Cannot read configuration file: .env-sync.json\n", $display);
    }

    public function testValidateConfigRejectsFileArguments(): void
    {
        [$status, $display] = $this->runCli([
            'command' => 'validate-config',
            'template' => '.env.example',
        ]);

        self::assertSame(2, $status);
        self::assertSame("The validate-config command does not accept file arguments.\n", $display);
    }

    public function testMissingTemplateIsUsageOrReadError(): void
    {
        [$status, $display] = $this->runCli(['command' => 'diff']);

        self::assertSame(2, $status);
        self::assertStringContainsString('Cannot read file: .env.example', $display);
    }

    public function testExplicitMissingTemplateReturnsReadableError(): void
    {
        [$status, $display] = $this->runCli([
            'command' => 'update',
            'template' => 'config/.env.dist',
            'target' => '.env',
        ]);

        self::assertSame(2, $status);
        self::assertSame("Cannot read file: config/.env.dist\n", $display);
        self::assertFileDoesNotExist('.env');
    }

    public function testVersionIsAvailableWithoutLoadingACommand(): void
    {
        [$status, $display] = $this->runCli(['--version' => true]);

        self::assertSame(0, $status);
        self::assertSame("env-sync 0.1.0\n", $display);
    }

    public function testHelpDocumentsTheSupportedCommandsAndOptions(): void
    {
        [$status, $display] = $this->runCli(['--help' => true]);

        self::assertSame(0, $status);
        self::assertStringContainsString('env-sync diff [--format=json] [--config=path] [template] [target]', $display);
        self::assertStringContainsString('env-sync update [--dry-run]', $display);
        self::assertStringContainsString('env-sync validate-config [--config=path]', $display);
        self::assertStringContainsString('--config=path', $display);
        self::assertStringContainsString('--verbose', $display);
        self::assertStringContainsString('--format=json', $display);
    }

    public function testInvalidOptionReturnsUsageError(): void
    {
        [$status, $display] = $this->runCli(['command' => 'update', '--unknown' => true]);

        self::assertSame(2, $status);
        self::assertSame("Unknown option. Run env-sync --help for usage.\n", $display);
    }

    public function testDryRunIsRejectedForDiff(): void
    {
        [$status, $display] = $this->runCli(['command' => 'diff', '--dry-run' => true]);

        self::assertSame(2, $status);
        self::assertSame("The --dry-run option is only available for update.\n", $display);
    }

    public function testJsonFormatIsRejectedForUpdate(): void
    {
        [$status, $display] = $this->runCli(['command' => 'update', '--format=json' => true]);

        self::assertSame(2, $status);
        self::assertSame("The --format option is only available for diff.\n", $display);
    }

    public function testRelativePathsAreResolvedFromApplicationWorkingDirectory(): void
    {
        self::assertTrue(mkdir('config'));
        file_put_contents('config/.env.dist', "A=default\n");

        [$status, $display] = $this->runCli([
            'command' => 'update',
            'template' => 'config/.env.dist',
            'target' => '.env',
        ]);

        self::assertSame(0, $status);
        self::assertFileExists($this->directory.'/.env');
        self::assertSame(".env updated: 1 variable added.\n", $display);
    }

    public function testSyntaxErrorReportsLineAndDoesNotModifyTarget(): void
    {
        file_put_contents('.env.example', "A=1\nBROKEN='oops\n");
        file_put_contents('.env', "A=private\n");

        [$status, $display] = $this->runCli(['command' => 'update']);

        self::assertSame(2, $status);
        self::assertStringContainsString('Line 2', $display);
        self::assertSame("A=private\n", file_get_contents('.env'));
    }

    public function testWriteFailureLeavesExistingFileUntouched(): void
    {
        file_put_contents('.env.example', "A=1\nB=2\n");
        file_put_contents('.env', "A=original-secret\n");
        $writer = new class implements FileWriter {
            public function write(string $path, string $contents): void
            {
                throw new \RuntimeException('simulated write failure');
            }
        };
        $service = new EnvSyncService(new Parser(), new Comparator(), new Updater(), $writer);
        $result = $service->planUpdate('.env.example', '.env');

        try {
            $service->write('.env', $result);
            self::fail('Expected a write failure.');
        } catch (\RuntimeException $exception) {
            self::assertSame('simulated write failure', $exception->getMessage());
        }
        self::assertSame("A=original-secret\n", file_get_contents('.env'));
    }

    /**
     * @param array<string, bool|string> $arguments
     *
     * @return array{int, string}
     */
    private function runCli(array $arguments): array
    {
        $application = ApplicationFactory::create();
        $cliArguments = [];
        if (isset($arguments['command']) && \is_string($arguments['command'])) {
            $cliArguments[] = $arguments['command'];
        }
        foreach ($arguments as $name => $value) {
            if ('command' !== $name && str_starts_with($name, '-') && true === $value) {
                $cliArguments[] = $name;
            }
        }
        if (isset($arguments['configuration']) && \is_string($arguments['configuration'])) {
            $cliArguments[] = '--config='.$arguments['configuration'];
        }
        foreach (['template', 'target'] as $name) {
            if (isset($arguments[$name]) && \is_string($arguments[$name])) {
                $cliArguments[] = $arguments[$name];
            }
        }

        $display = '';
        $write = static function (string $message) use (&$display): void {
            $display .= $message;
        };
        $status = $application->run($cliArguments, $write, $write);

        return [$status, $display];
    }

    private function removeDirectory(string $directory): void
    {
        foreach (new \FilesystemIterator($directory) as $file) {
            if (!$file instanceof \SplFileInfo) {
                throw new \RuntimeException('Unexpected temporary directory entry.');
            }
            if ($file->isDir()) {
                $this->removeDirectory($file->getPathname());
            } else {
                unlink($file->getPathname());
            }
        }
        rmdir($directory);
    }
}
