<?php

declare(strict_types=1);

namespace Alianet\EnvSync\Application;

use Alianet\EnvSync\Diff\Comparator;
use Alianet\EnvSync\Diff\ComparisonRules;
use Alianet\EnvSync\Diff\DiffResult;
use Alianet\EnvSync\Document\Document;
use Alianet\EnvSync\Document\Parser;
use Alianet\EnvSync\Document\Updater;
use Alianet\EnvSync\Document\UpdateResult;
use Alianet\EnvSync\Exception\FileReadException;
use Alianet\EnvSync\Filesystem\FileWriter;

final readonly class EnvSyncService
{
    public function __construct(
        private Parser $parser,
        private Comparator $comparator,
        private Updater $updater,
        private FileWriter $writer,
    ) {
    }

    public function diff(string $templatePath, string $targetPath, ?ComparisonRules $rules = null): DiffResult
    {
        return $this->comparator->compare(
            $this->readRequired($templatePath),
            $this->readRequired($targetPath),
            $rules,
        );
    }

    public function planUpdate(string $templatePath, string $targetPath): UpdateResult
    {
        $template = $this->readRequired($templatePath);
        $target = file_exists($targetPath) ? $this->readRequired($targetPath) : new Document([]);

        return $this->updater->update($template, $target);
    }

    public function write(string $path, UpdateResult $result): void
    {
        $this->writer->write($path, $result->document->render());
    }

    private function readRequired(string $path): Document
    {
        if (!is_file($path) || !is_readable($path)) {
            throw new FileReadException(\sprintf('Cannot read file: %s', $path));
        }
        $contents = file_get_contents($path);
        if (false === $contents) {
            throw new FileReadException(\sprintf('Cannot read file: %s', $path));
        }

        return $this->parser->parse($contents);
    }
}
