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
     * A registry knows the company, but it is marked inactive (closed, ended, not an active VAT payer).
     */
    case Inactive = 'inactive';

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
