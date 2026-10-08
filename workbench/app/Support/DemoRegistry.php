<?php

declare(strict_types=1);

namespace Workbench\App\Support;

use Asignua\FilamentVatId\Contracts\CompanyRegistry;
use Asignua\FilamentVatId\Data\CompanyData;
use Asignua\FilamentVatId\Enums\TaxIdType;

/**
 * Canned answers for the README screenshots, registered only when FILAMENT_VAT_ID_DEMO=true (see AdminPanelProvider).
 * Known: PL 5260250995, PL 7740001454, CZ 45274649; any other valid number is "not found".
 */
class DemoRegistry implements CompanyRegistry
{
    private const TYPES = [
        'PL' => [TaxIdType::EuVat, TaxIdType::PlNip],
        'CZ' => [TaxIdType::EuVat, TaxIdType::CzIco],
    ];

    public function supports(string $country, TaxIdType $type): bool
    {
        return in_array($type, self::TYPES[$country] ?? [], true);
    }

    public function canVerify(string $country, TaxIdType $type): bool
    {
        return false;
    }

    public function lookup(string $country, TaxIdType $type, string $number): ?CompanyData
    {
        return match ($country.$number) {
            'PL5260250995' => new CompanyData(
                name: 'Orange Polska S.A.',
                source: 'demo',
                address: 'Aleje Jerozolimskie 160, 02-326 Warszawa',
                street: 'Aleje Jerozolimskie 160',
                city: 'Warszawa',
                postcode: '02-326',
                country: 'PL',
                vatNumber: '5260250995',
                regon: '012100784',
                registryId: '0000010681',
                active: true,
                status: 'Czynny',
                bankAccounts: ['PL60102010260000123456789012'],
            ),
            'PL7740001454' => new CompanyData(
                name: 'Polski Koncern Naftowy ORLEN S.A.',
                source: 'demo',
                address: 'ul. Chemików 7, 09-411 Płock',
                street: 'ul. Chemików 7',
                city: 'Płock',
                postcode: '09-411',
                country: 'PL',
                vatNumber: '7740001454',
                regon: '610188201',
                registryId: '0000028860',
                active: true,
                status: 'Czynny',
                bankAccounts: ['PL47114020040000301234567890'],
            ),
            'CZ45274649' => new CompanyData(
                name: 'ČEZ, a. s.',
                source: 'demo',
                address: 'Duhová 1444/2, 140 53 Praha 4',
                street: 'Duhová 1444/2',
                city: 'Praha 4',
                postcode: '140 53',
                country: 'CZ',
                vatNumber: 'CZ45274649',
                ico: '45274649',
                active: true,
            ),
            default => null,
        };
    }
}
