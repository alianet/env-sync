<?php

declare(strict_types=1);

namespace Alianet\EnvSync\Tests\Diff;

use Alianet\EnvSync\Diff\Comparator;
use Alianet\EnvSync\Diff\ComparisonRules;
use Alianet\EnvSync\Diff\ConditionalRequirement;
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

    public function testFindsRequiredValuesThatWereNotChanged(): void
    {
        $result = $this->compare(
            "APP_ADDRESS=http://set-to-your-site\nTOKEN=replace-me\nOPTIONAL=default\n",
            "APP_ADDRESS=\"http://set-to-your-site\" # forgotten\nTOKEN=changed\nOPTIONAL=default\n",
            new ComparisonRules(requiredChangedKeys: ['APP_ADDRESS', 'TOKEN']),
        );

        self::assertSame(['APP_ADDRESS'], $result->unchangedRequired);
        self::assertTrue($result->hasDifferences());
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

    public function testAppliesOnlyRequirementsForTheMatchingCondition(): void
    {
        $rules = new ComparisonRules(conditionalRequirements: [
            new ConditionalRequirement(
                'ATLASSIAN_ACCOUNT_TYPE',
                'individual',
                ['ATLASSIAN_EMAIL', 'ATLASSIAN_API_TOKEN'],
                ['ATLASSIAN_API_TOKEN'],
            ),
            new ConditionalRequirement(
                'ATLASSIAN_ACCOUNT_TYPE',
                'company',
                ['ATLASSIAN_CLIENT_ID', 'ATLASSIAN_CLIENT_SECRET', 'ATLASSIAN_REDIRECT_URI', 'SESSION_ENCRYPTION_KEY'],
                ['ATLASSIAN_CLIENT_ID', 'ATLASSIAN_CLIENT_SECRET', 'SESSION_ENCRYPTION_KEY'],
            ),
        ]);
        $template = <<<'DOTENV'
ATLASSIAN_ACCOUNT_TYPE=individual
ATLASSIAN_EMAIL=your-email
ATLASSIAN_API_TOKEN=replace-me
ATLASSIAN_CLIENT_ID=replace-me
ATLASSIAN_CLIENT_SECRET=replace-me
ATLASSIAN_REDIRECT_URI=http://localhost/callback
SESSION_ENCRYPTION_KEY=replace-me
DOTENV;
        $target = <<<'DOTENV'
ATLASSIAN_ACCOUNT_TYPE=company
ATLASSIAN_CLIENT_ID=actual-id
ATLASSIAN_CLIENT_SECRET=replace-me
ATLASSIAN_REDIRECT_URI=http://localhost/callback
DOTENV;

        $result = $this->compare($template, $target, $rules);

        self::assertSame(['SESSION_ENCRYPTION_KEY'], $result->missing);
        self::assertSame(['ATLASSIAN_CLIENT_SECRET'], $result->unchangedRequired);
        self::assertSame([], $result->unmatchedConditionKeys);
    }

    public function testReportsConditionKeyWhenNoBranchMatches(): void
    {
        $rules = new ComparisonRules(conditionalRequirements: [
            new ConditionalRequirement('ACCOUNT_TYPE', 'individual', ['EMAIL']),
            new ConditionalRequirement('ACCOUNT_TYPE', 'company', ['CLIENT_ID']),
        ]);

        $result = $this->compare(
            "ACCOUNT_TYPE=unsupported\nEMAIL=placeholder\nCLIENT_ID=placeholder\n",
            "ACCOUNT_TYPE=secret-unsupported-value\n",
            $rules,
        );

        self::assertSame([], $result->missing);
        self::assertSame(['ACCOUNT_TYPE'], $result->unmatchedConditionKeys);
        self::assertTrue($result->hasDifferences());
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
