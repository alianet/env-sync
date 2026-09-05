<?php

declare(strict_types=1);

namespace Alianet\EnvSync\Document;

final readonly class AssignmentLine extends Line
{
    public function __construct(string $content, string $ending, public string $key)
    {
        parent::__construct($content, $ending);
    }

    public function withEnding(string $ending): self
    {
        return new self($this->content, $ending, $this->key);
    }
}
