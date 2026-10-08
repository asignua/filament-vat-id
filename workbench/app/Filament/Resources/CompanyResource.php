<?php

declare(strict_types=1);

namespace Workbench\App\Filament\Resources;

use Asignua\FilamentVatId\Enums\TaxIdType;
use Asignua\FilamentVatId\Forms\Components\TaxIdInput;
use Asignua\FilamentVatId\Infolists\Components\TaxIdEntry;
use Asignua\FilamentVatId\Support\TaxIdValidator;
use BackedEnum;
use Filament\Actions\ActionGroup;
use Filament\Actions\EditAction;
use Filament\Actions\ViewAction;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Infolists\Components\TextEntry;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Workbench\App\Filament\Resources\CompanyResource\Pages\CreateCompany;
use Workbench\App\Filament\Resources\CompanyResource\Pages\EditCompany;
use Workbench\App\Filament\Resources\CompanyResource\Pages\ListCompanies;
use Workbench\App\Filament\Resources\CompanyResource\Pages\ViewCompany;
use Workbench\App\Models\Company;

class CompanyResource extends Resource
{
    protected static ?string $model = Company::class;

    protected static ?string $modelLabel = 'counterparty';

    protected static ?string $pluralModelLabel = 'counterparties';

    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-briefcase';

    public const COUNTRIES = [
        'PL' => 'Poland',
        'CZ' => 'Czechia',
        'DE' => 'Germany',
        'UA' => 'Ukraine',
        'IT' => 'Italy',
        'ES' => 'Spain',
    ];

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            Select::make('country')->options(self::COUNTRIES)->required()->live(),
            Select::make('type')
                ->label('Identifier type')
                ->options(collect(TaxIdType::cases())->mapWithKeys(fn (TaxIdType $type): array => [$type->value => $type->label()])->all())
                ->default(TaxIdType::EuVat->value)
                ->required()
                ->live(),
            TaxIdInput::make('tax_id')->label('VAT / Tax ID')
                ->type(fn (Get $get): TaxIdType => TaxIdType::tryFrom((string) $get('type')) ?? TaxIdType::EuVat)
                ->countryField('country')
                ->required()
                ->lookup()
                ->fill([
                    'name' => 'name',
                    'address' => 'address',
                    'regon' => 'regon',
                    'bankAccounts.0' => 'iban',
                ])
                ->columnSpanFull(),
            TextInput::make('name')->required()->maxLength(160),
            TextInput::make('address')->maxLength(200),
            TextInput::make('regon')->label('REGON'),
            TextInput::make('iban')->label('Bank account (IBAN)'),
        ]);
    }

    public static function infolist(Schema $schema): Schema
    {
        return $schema->components([
            TextEntry::make('name'),
            TextEntry::make('country')->formatStateUsing(fn (string $state): string => self::COUNTRIES[$state] ?? $state),
            TaxIdEntry::make('tax_id')->label('VAT / Tax ID')
                ->label('Tax ID')
                ->type(fn (Company $record): TaxIdType => TaxIdType::from($record->type))
                ->countryField('country'),
            TextEntry::make('type')->formatStateUsing(fn (string $state): string => TaxIdType::from($state)->label())->badge(),
            TextEntry::make('address')->placeholder('-'),
            TextEntry::make('regon')->label('REGON')->placeholder('-'),
            TextEntry::make('iban')->label('Bank account (IBAN)')->placeholder('-')->columnSpanFull(),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('name')->searchable()->sortable(),
                TextColumn::make('country')->badge()->sortable(),
                TextColumn::make('type')
                    ->formatStateUsing(fn (string $state): string => TaxIdType::from($state)->label())
                    ->badge()
                    ->color('gray'),
                TextColumn::make('tax_id')
                    ->label('Tax ID')
                    ->fontFamily('mono')
                    ->copyable()
                    ->formatStateUsing(fn (?string $state, Company $record): ?string => $state === null ? null : TaxIdValidator::format($state, TaxIdType::from($record->type), $record->country)),
                TextColumn::make('address')->limit(40)->toggleable(),
                TextColumn::make('regon')->label('REGON')->toggleable(isToggledHiddenByDefault: true),
            ])
            ->recordActions([ActionGroup::make([ViewAction::make(), EditAction::make()])])
            ->defaultSort('name');
    }

    public static function getPages(): array
    {
        return [
            'index' => ListCompanies::route('/'),
            'create' => CreateCompany::route('/create'),
            'view' => ViewCompany::route('/{record}'),
            'edit' => EditCompany::route('/{record}/edit'),
        ];
    }
}
