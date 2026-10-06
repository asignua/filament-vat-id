<?php

declare(strict_types=1);

namespace Asignua\FilamentVatId\Enums;

enum VerificationStatus: string
{
    /**
     * A registry knows the company.
     */
    case Verified = 'verified';

    /**
     * The registries answered that there is no such company.
     */
    case NotFound = 'not_found';

    /**
     * No registry could answer (see `on_unavailable`).
     */
    case Unavailable = 'unavailable';

    /**
     * No registry supports this country / type: nothing to check.
     */
    case Skipped = 'skipped';
}
