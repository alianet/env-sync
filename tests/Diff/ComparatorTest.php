<?php

declare(strict_types=1);

namespace Alianet\EnvSync\Tests\Diff;

use Alianet\EnvSync\Diff\Comparator;
use Alianet\EnvSync\Diff\ComparisonRules;
use Alianet\EnvSync\Document\Parser;
use PHPUnit\Framework\TestCase;

final class ComparatorTest extends TestCase
{
    public function testFindsMissingAndExtraKeys(): void
    {
        $result = $this->compare("SHARED=template\nMISSING=default\n", "SHARED=local\nEXTRA=secret\n");

        self::assertSame(['MISSING'], $result->missing);
        self::assertSame(['EXTRA'], $result->extra);
    }

    public function testDifferentValuesAreStillCompatible(): void
    {
        self::assertFalse($this->compare("TOKEN=public-default\n", "TOKEN=top-secret\n")->hasDifferences());
    }

    public function testAllowedExtraKeysAreNotReportedAsDifferences(): void
    {
        $result = $this->compare(
            "SHARED=template\n",
            "SHARED=local\nALLOWED=secret\nUNEXPECTED=secret\n",
            new ComparisonRules(['ALLOWED']),
        );

        self::assertSame(['UNEXPECTED'], $result->extra);
        self::assertTrue($result->hasDifferences());
    }

    public function testAllowedExtraPatternsUseCaseSensitiveWholeKeyGlobs(): void
    {
        $result = $this->compare(
            "SHARED=template\n",
            "SHARED=local\nLOCAL_CACHE=secret\nCACHE_A=secret\nCACHE_LONG=secret\nlocal_cache=secret\n",
            new ComparisonRules(allowedExtraPatterns: ['LOCAL_*', 'CACHE_?']),
        );

        self::assertSame(['CACHE_LONG', 'local_cache'], $result->extra);
    }

    public function testFindsDuplicatesOnBothSides(): void
    {
        $result = $this->compare("A=1\nA=2\n", "B=1\nB=2\n");

        self::assertSame(['A'], $result->templateDuplicates);
        self::assertSame(['B'], $result->targetDuplicates);
    }

    private function compare(
        string $template,
        string $target,
        ?ComparisonRules $rules = null,
    ): \Alianet\EnvSync\Diff\DiffResult {
        $parser = new Parser();

        return (new Comparator())->compare($parser->parse($template), $parser->parse($target), $rules);
    }
}
