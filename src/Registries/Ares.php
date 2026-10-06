<?php

declare(strict_types=1);

namespace Asignua\FilamentVatId\Registries;

use Asignua\FilamentVatId\Data\CompanyData;
use Asignua\FilamentVatId\Enums\TaxIdType;
use Asignua\FilamentVatId\Exceptions\NumberNotSupported;
use Asignua\FilamentVatId\Exceptions\RegistryUnavailable;
use Asignua\FilamentVatId\Support\TaxIdValidator;

/**
 * ARES, the Czech Ministry of Finance's administrative register of economic entities, REST API. No key. Looks a
 * company up by IČO (8 digits): the name, the registered office and the DIČ. A DIČ of an individual (9-10 digits,
 * a birth number) cannot be resolved here and yields "not found".
 */
class Ares extends AbstractRegistry
{
    public function name(): string
    {
        return 'ares';
    }

    public function supports(string $country, TaxIdType $type): bool
    {
        return $country === 'CZ' && in_array($type, [TaxIdType::CzIco, TaxIdType::CzDic, TaxIdType::EuVat], true);
    }

    public function canVerify(string $country, TaxIdType $type): bool
    {
        return $country === 'CZ' && in_array($type, [TaxIdType::CzIco, TaxIdType::CzDic], true);
    }

    public function lookup(string $country, TaxIdType $type, string $number): ?CompanyData
    {
        $ico = TaxIdValidator::normalize($number, TaxIdType::CzIco);

        if (preg_match('/^\d{8}$/', $ico) !== 1) {
            throw new NumberNotSupported($this->name(), 'ARES looks up IČO (8 digits); a birth-number DIČ cannot be resolved.');
        }

        $url = rtrim($this->configString('ares.url'), '/').'/'.$ico;

        $response = $this->send(fn () => $this->http()->acceptJson()->get($url));

        if ($response->status() === 404) {
            return null;
        }

        if (!$response->successful()) {
            throw new RegistryUnavailable($this->name(), 'HTTP '.$response->status());
        }

        $json = $response->json();

        if (!is_array($json) || !isset($json['obchodniJmeno'])) {
            throw new RegistryUnavailable($this->name(), 'Unexpected response');
        }

        $seat = is_array($json['sidlo'] ?? null) ? $json['sidlo'] : [];
        $street = self::street($seat);
        $postcode = isset($seat['psc']) && is_numeric($seat['psc']) ? str_pad((string) $seat['psc'], 5, '0', STR_PAD_LEFT) : null;
        $ended = self::clean($json['datumZaniku'] ?? null);

        return new CompanyData(
            name: self::clean($json['obchodniJmeno']) ?? '',
            source: $this->name(),
            address: self::clean($seat['textovaAdresa'] ?? null),
            street: $street,
            city: self::clean($seat['nazevObce'] ?? null),
            postcode: $postcode,
            country: 'CZ',
            vatNumber: self::clean($json['dic'] ?? null),
            ico: self::clean($json['ico'] ?? null) ?? $ico,
            registryId: self::clean($json['ico'] ?? null) ?? $ico,
            active: $ended === null,
            status: $ended === null ? 'active' : 'ended',
            raw: $json,
        );
    }

    /**
     * @param array<array-key, mixed> $seat
     */
    private static function street(array $seat): ?string
    {
        $name = self::clean($seat['nazevUlice'] ?? null) ?? self::clean($seat['nazevCastiObce'] ?? null);
        $house = self::clean($seat['cisloDomovni'] ?? null);
        $orientation = self::clean($seat['cisloOrientacni'] ?? null);

        $number = $house === null ? null : ($orientation === null ? $house : $house.'/'.$orientation);

        $street = trim(($name ?? '').' '.($number ?? ''));

        return $street === '' ? null : $street;
    }
}
