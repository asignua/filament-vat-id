<?php

declare(strict_types=1);

namespace Workbench\App\Filament\Resources\CompanyResource\Pages;

use Filament\Resources\Pages\CreateRecord;
use Workbench\App\Filament\Resources\CompanyResource;

class CreateCompany extends CreateRecord
{
    protected static string $resource = CompanyResource::class;
}
