<?php

declare(strict_types=1);

namespace Asignua\FilamentVatId\Registries;

use Asignua\FilamentVatId\Data\CompanyData;
use Asignua\FilamentVatId\Enums\TaxIdType;
use Asignua\FilamentVatId\Exceptions\RegistryUnavailable;
use Asignua\FilamentVatId\Support\EuVatNumber;

/**
 * VIES (the European Commission's VAT Information Exchange System), REST API. No key. Confirms that a VAT number
 * is registered and, for most member states, returns the name and address (`---` means "not provided").
 *
 * Availability depends on the member state's own service: `MS_UNAVAILABLE` is common (DE, ES, …) and means
 * "try again later", never "invalid".
 */
class Vies extends AbstractRegistry
{
    /** Errors that mean "no answer", as opposed to "no such number". */
    private const array UNAVAILABLE = [
        'MS_UNAVAILABLE', 'MS_MAX_CONCURRENT_REQ', 'GLOBAL_MAX_CONCURRENT_REQ', 'SERVICE_UNAVAILABLE', 'TIMEOUT',
        'MS_MAX_CONCURRENT_REQ_TIME', 'GLOBAL_MAX_CONCURRENT_REQ_TIME',
    ];

    public function name(): string
    {
        return 'vies';
    }

    public function supports(string $country, TaxIdType $type): bool
    {
        return $type === TaxIdType::EuVat && EuVatNumber::prefix($country) !== null;
    }

    public function lookup(string $country, string $number): ?CompanyData
    {
        $prefix = EuVatNumber::prefix($country);

        if ($prefix === null) {
            return null;
        }

        $number = strtoupper((string) preg_replace('/[^A-Za-z0-9+*]/', '', $number));

        if (str_starts_with($number, $prefix)) {
            $number = substr($number, 2);
        }

        $response = $this->send(fn () => $this->http()->acceptJson()->asJson()->post($this->configString('vies.url'), [
            'countryCode' => $prefix,
            'vatNumber' => $number,
        ]));

        $json = $response->json();

        if ($response->serverError() || !is_array($json)) {
            throw new RegistryUnavailable($this->name(), 'HTTP '.$response->status());
        }

        foreach ((array) ($json['errorWrappers'] ?? []) as $wrapper) {
            $error = is_array($wrapper) ? (string) ($wrapper['error'] ?? '') : '';

            if ($error === 'INVALID_INPUT') {
                return null;
            }

            throw new RegistryUnavailable($this->name(), $error);
        }

        if (($json['actionSucceed'] ?? true) === false || $response->clientError()) {
            throw new RegistryUnavailable($this->name(), 'HTTP '.$response->status());
        }

        // Older gateways report the failure in `userError` instead of `errorWrappers`.
        $userError = (string) ($json['userError'] ?? '');

        if (in_array($userError, self::UNAVAILABLE, true)) {
            throw new RegistryUnavailable($this->name(), $userError);
        }

        if (($json['valid'] ?? false) !== true) {
            return null;
        }

        $address = self::clean($json['address'] ?? null);

        return new CompanyData(
            name: self::clean($json['name'] ?? null) ?? '',
            source: $this->name(),
            address: $address === null ? null : trim((string) preg_replace('/\s*\R\s*/u', ', ', $address)),
            country: $prefix === 'EL' ? 'GR' : $prefix,
            vatNumber: $prefix.$number,
            active: true,
            status: 'valid',
            raw: $json,
        );
    }
}
