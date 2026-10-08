<?php

declare(strict_types=1);

namespace Workbench\Database\Seeders;

use Illuminate\Database\Seeder;
use Workbench\App\Models\Company;
use Workbench\App\Models\User;

/**
 * Screenshot data (`vendor/bin/testbench db:seed --class='Workbench\Database\Seeders\DemoSeeder'` after
 * `workbench:build`). Log in as emma@example.com / password. Every number passes the offline checksum.
 * Lookup demo (run with FILAMENT_VAT_ID_DEMO=true): PL 5260250995, PL 7740001454, CZ 45274649.
 */
class DemoSeeder extends Seeder
{
    public function run(): void
    {
        User::query()->firstOrCreate(['email' => 'emma@example.com'], ['name' => 'Emma Carter', 'password' => 'password']);

        $companies = [
            ['PL', 'eu_vat', '5260250995', 'Orange Polska S.A.', 'Aleje Jerozolimskie 160, 02-326 Warszawa', '012100784', 'PL60102010260000123456789012'],
            ['PL', 'pl_nip', '7740001454', 'Polski Koncern Naftowy ORLEN S.A.', 'ul. Chemików 7, 09-411 Płock', '610188201', 'PL47114020040000301234567890'],
            ['CZ', 'eu_vat', '45274649', 'ČEZ, a. s.', 'Duhová 1444/2, 140 53 Praha 4', null, null],
            ['CZ', 'cz_ico', '27082440', 'Alza.cz a.s.', 'Jateční 1521/33, 170 00 Praha 7', null, null],
            ['DE', 'eu_vat', '811120001', 'Rhein Maschinenbau GmbH', 'Königsallee 12, 40212 Düsseldorf', null, 'DE89370400440532013000'],
            ['UA', 'ua_edrpou', '20077720', 'NJSC Naftogaz of Ukraine', 'Bohdana Khmelnytskoho St, 6, Kyiv', null, 'UA543052990000026003012345678'],
            ['IT', 'eu_vat', '00743110157', 'Milano Imballaggi S.r.l.', 'Via Roma 21, 20121 Milano', null, null],
            ['ES', 'eu_vat', 'A28015865', 'Iberia Fresh S.L.', 'Calle de Alcalá 48, 28014 Madrid', null, 'ES9121000418450200051332'],
        ];

        foreach ($companies as [$country, $type, $taxId, $name, $address, $regon, $iban]) {
            Company::query()->firstOrCreate(
                ['country' => $country, 'tax_id' => $taxId],
                compact('type', 'name', 'address', 'regon', 'iban'),
            );
        }
    }
}
