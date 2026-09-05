<?php

declare(strict_types=1);

namespace Alianet\EnvSync\Console;

use Alianet\EnvSync\Application\ConfigurationLoader;
use Alianet\EnvSync\Application\EnvSyncService;
use Alianet\EnvSync\Diff\Comparator;
use Alianet\EnvSync\Document\Parser;
use Alianet\EnvSync\Document\Updater;
use Alianet\EnvSync\Filesystem\AtomicFileWriter;

final class ApplicationFactory
{
    public static function create(): Application
    {
        $service = new EnvSyncService(new Parser(), new Comparator(), new Updater(), new AtomicFileWriter());

        return new Application($service, new PathResolver(), new ConfigurationLoader(), new ConsoleInputParser());
    }
}
