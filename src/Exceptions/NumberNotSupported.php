<?php

declare(strict_types=1);

namespace Asignua\FilamentVatId\Exceptions;

use RuntimeException;

/**
 * A registry cannot look this particular number up (for example ARES and a birth-number DIČ of an individual).
 * It is neither "not found" nor "unavailable": the number is simply outside what the registry covers.
 */
class NumberNotSupported extends RuntimeException
{
    public function __construct(public readonly string $registry, string $reason = '')
    {
        parent::__construct(trim("Registry [{$registry}] cannot look this number up. {$reason}"));
    }
}
