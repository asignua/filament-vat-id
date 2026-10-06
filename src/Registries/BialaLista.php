<?php

declare(strict_types=1);

namespace Asignua\FilamentVatId\Registries;

use Asignua\FilamentVatId\Data\CompanyData;
use Asignua\FilamentVatId\Enums\TaxIdType;
use Asignua\FilamentVatId\Exceptions\RegistryUnavailable;

/**
 * The Polish Ministry of Finance "white list" of VAT payers (Biała lista), REST API. No key. Returns the name,
 * address, VAT status, REGON, KRS and the registered bank accounts. The API is rate limited (a handful of
 * requests per second and a daily quota per IP): HTTP 429 is reported as "unavailable".
 */
class BialaLista extends AbstractRegistry
{
    public function name(): string
    {
        return 'biala_lista';
    }

    public function supports(string $country, TaxIdType $type): bool
    {
        return $country === 'PL' && in_array($type, [TaxIdType::EuVat, TaxIdType::PlNip, TaxIdType::PlRegon], true);
    }

    public function lookup(string $country, string $number): ?CompanyData
    {
        $number = (string) preg_replace('/\D/', '', (string) preg_replace('/^PL/i', '', $number));

        $kind = match (strlen($number)) {
            10 => 'nip',
            9, 14 => 'regon',
            default => null,
        };

        if ($kind === null) {
            return null;
        }

        $url = rtrim($this->configString('biala_lista.url'), '/')."/{$kind}/{$number}";

        $response = $this->send(fn () => $this->http()->acceptJson()->get($url, ['date' => now('Europe/Warsaw')->format('Y-m-d')]));

        // 400 = the API rejected the number itself: treat as "no such company".
        if ($response->status() === 400 || $response->status() === 404) {
            return null;
        }

        if (!$response->successful()) {
            throw new RegistryUnavailable($this->name(), 'HTTP '.$response->status());
        }

        $json = $response->json();
        $subject = is_array($json) && is_array($json['result'] ?? null) ? ($json['result']['subject'] ?? null) : false;

        if ($subject === false) {
            throw new RegistryUnavailable($this->name(), 'Unexpected response');
        }

        if (!is_array($subject)) {
            return null;
        }

        $address = self::clean($subject['workingAddress'] ?? null) ?? self::clean($subject['residenceAddress'] ?? null);
        $street = $postcode = $city = null;

        if ($address !== null && preg_match('/^(.*),\s*(\d{2}-\d{3})\s+(.+)$/u', $address, $m) === 1) {
            [, $street, $postcode, $city] = $m;
        }

        $nip = self::clean($subject['nip'] ?? null);
        $status = self::clean($subject['statusVat'] ?? null);

        $accounts = [];

        foreach ((array) ($subject['accountNumbers'] ?? []) as $account) {
            if (is_string($account) && preg_match('/^\d{26}$/', $account) === 1) {
                $accounts[] = 'PL'.$account;
            }
        }

        return new CompanyData(
            name: self::clean($subject['name'] ?? null) ?? '',
            source: $this->name(),
            address: $address,
            street: $street,
            city: $city,
            postcode: $postcode,
            country: 'PL',
            vatNumber: $nip === null ? null : 'PL'.$nip,
            regon: self::clean($subject['regon'] ?? null),
            registryId: self::clean($subject['krs'] ?? null),
            active: $status === null ? null : $status === 'Czynny',
            status: $status,
            bankAccounts: $accounts,
            raw: $json,
        );
    }
}
