<?php

declare(strict_types=1);

namespace Alianet\EnvSync\Document;

final readonly class CommentLine extends Line
{
    public function withEnding(string $ending): self
    {
        return new self($this->content, $ending);
    }
}
