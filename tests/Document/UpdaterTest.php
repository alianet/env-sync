<?php

declare(strict_types=1);

namespace Alianet\EnvSync\Tests\Document;

use Alianet\EnvSync\Document\Parser;
use Alianet\EnvSync\Document\Updater;
use Alianet\EnvSync\Exception\UpdateException;
use PHPUnit\Framework\TestCase;

final class UpdaterTest extends TestCase
{
    public function testPreservesExistingValuesAndAddsDefaultsToMatchingSection(): void
    {
        $parser = new Parser();
        $result = (new Updater())->update(
            $parser->parse("EXISTING=template\nNEW=default-value\n"),
            $parser->parse("# local\nEXISTING=actual-secret\n"),
        );

        self::assertSame(['NEW'], $result->addedKeys);
        self::assertSame("# local\nEXISTING=actual-secret\nNEW=default-value\n", $result->document->render());
    }

    public function testUsesTargetCrLfWhenAppending(): void
    {
        $parser = new Parser();
        $result = (new Updater())->update($parser->parse("A=1\nB=2\n"), $parser->parse("A=local\r\n"));

        self::assertSame("A=local\r\nB=2\r\n", $result->document->render());
    }

    public function testPlacesMissingKeysInTemplateOrderInsideMatchingSections(): void
    {
        $parser = new Parser();
        $result = (new Updater())->update(
            $parser->parse("# App\nAPP_NAME=app\nAPP_ENV=prod\nAPP_DEBUG=0\n\n# Database\nDB_HOST=localhost\nDB_PORT=3306\n"),
            $parser->parse("# App\nAPP_NAME=local\nAPP_DEBUG=1\n\n# Database\nDB_HOST=db.internal\n"),
        );

        self::assertSame(['APP_ENV', 'DB_PORT'], $result->addedKeys);
        self::assertSame(
            "# App\nAPP_NAME=local\nAPP_ENV=prod\nAPP_DEBUG=1\n\n# Database\nDB_HOST=db.internal\nDB_PORT=3306\n",
            $result->document->render(),
        );
    }

    public function testMatchesAnEmptyTargetSectionByItsHeading(): void
    {
        $parser = new Parser();
        $result = (new Updater())->update(
            $parser->parse("# Mail\nMAIL_HOST=localhost\n\n# Database\nDB_HOST=localhost\n"),
            $parser->parse("# Mail\n\n# Database\nDB_HOST=db.internal\n"),
        );

        self::assertSame("# Mail\nMAIL_HOST=localhost\n\n# Database\nDB_HOST=db.internal\n", $result->document->render());
    }

    public function testFallsBackToAddedSectionWhenNoTargetSectionMatches(): void
    {
        $parser = new Parser();
        $result = (new Updater())->update(
            $parser->parse("# Mail\nMAIL_HOST=localhost\n"),
            $parser->parse("# App\nAPP_ENV=local\n"),
        );

        self::assertSame(
            "# App\nAPP_ENV=local\n\n# Added by env-sync\nMAIL_HOST=localhost\n",
            $result->document->render(),
        );
    }

    public function testRefusesAmbiguousDuplicates(): void
    {
        $this->expectException(UpdateException::class);
        $parser = new Parser();
        (new Updater())->update($parser->parse("A=1\n"), $parser->parse("A=1\nA=2\n"));
    }
}
