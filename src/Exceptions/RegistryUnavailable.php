<?php

declare(strict_types=1);

namespace Asignua\FilamentVatId\Exceptions;

use RuntimeException;
use Throwable;

/**
 * A registry could not give an answer (timeout, 5xx, member-state service down, rate limit, bad credentials).
 * This is not "the number does not exist": the caller decides what to do (see `on_unavailable`).
 */
class RegistryUnavailable extends RuntimeException
{
    public function __construct(public readonly string $registry, string $reason = '', ?Throwable $previous = null)
    {
        parent::__construct(trim("Registry [{$registry}] is unavailable. {$reason}"), 0, $previous);
    }
}
