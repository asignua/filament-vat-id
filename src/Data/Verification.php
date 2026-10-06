<?php

declare(strict_types=1);

namespace Asignua\FilamentVatId\Data;

use Asignua\FilamentVatId\Enums\VerificationStatus;

/**
 * The outcome of a remote check.
 */
final readonly class Verification
{
    public function __construct(
        public VerificationStatus $status,
        public ?CompanyData $company = null,
        public ?string $reason = null,
    ) {}
}
