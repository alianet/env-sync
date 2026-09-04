<?php

declare(strict_types=1);

namespace Alianet\EnvSync\Document;

final readonly class UpdateResult
{
    /** @param list<string> $addedKeys */
    public function __construct(public Document $document, public array $addedKeys)
    {
    }
}
