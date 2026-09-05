<?php

declare(strict_types=1);

namespace Alianet\EnvSync\Document;

abstract readonly class Line
{
    public function __construct(public string $content, public string $ending)
    {
    }

    final public function render(): string
    {
        return $this->content.$this->ending;
    }

    abstract public function withEnding(string $ending): self;
}
