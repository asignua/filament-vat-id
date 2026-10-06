<?php

declare(strict_types=1);

namespace Asignua\FilamentVatId\Rules;

use Asignua\FilamentVatId\Enums\TaxIdType;
use Asignua\FilamentVatId\Support\TaxIdValidator;
use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

/**
 * The value must be a well-formed tax identifier of the given type (format and checksum, offline). A blank value
 * passes: combine with `required`.
 *
 *     ['required', new TaxId(TaxIdType::PlNip)]
 *     ['nullable', TaxId::make(TaxIdType::EuVat)->country(fn () => request('country'))]
 *
 * @phpstan-consistent-constructor
 */
class TaxId implements ValidationRule
{
    protected string|Closure|null $country = null;

    public function __construct(protected TaxIdType $type = TaxIdType::EuVat, string|Closure|null $country = null)
    {
        $this->country = $country;
    }

    public static function make(TaxIdType $type = TaxIdType::EuVat): static
    {
        return new static($type);
    }

    /**
     * The country of an EU VAT number given without its prefix (ISO 3166-1 alpha-2).
     */
    public function country(string|Closure|null $country): static
    {
        $this->country = $country;

        return $this;
    }

    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if ($value === null || (is_string($value) && trim($value) === '')) {
            return;
        }

        $country = $this->country instanceof Closure ? ($this->country)() : $this->country;

        if (!is_string($value) && !is_int($value)) {
            $fail('filament-vat-id::filament-vat-id.validation.invalid')->translate(['type' => $this->type->label()]);

            return;
        }

        if (!TaxIdValidator::isValid((string) $value, $this->type, is_string($country) ? $country : null)) {
            $fail('filament-vat-id::filament-vat-id.validation.invalid')->translate(['type' => $this->type->label()]);
        }
    }
}
