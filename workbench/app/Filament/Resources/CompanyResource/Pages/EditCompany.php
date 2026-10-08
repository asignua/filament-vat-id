<?php

declare(strict_types=1);

namespace Workbench\App\Filament\Resources\CompanyResource\Pages;

use Filament\Resources\Pages\EditRecord;
use Workbench\App\Filament\Resources\CompanyResource;

class EditCompany extends EditRecord
{
    protected static string $resource = CompanyResource::class;
}
