<?php

declare(strict_types=1);

namespace Alianet\EnvSync\Exception;

final class ParseException extends \RuntimeException implements UserFacingException
{
    public function __construct(public readonly int $lineNumber, string $reason)
    {
        parent::__construct(\sprintf('Line %d: %s', $lineNumber, $reason));
    }
}
