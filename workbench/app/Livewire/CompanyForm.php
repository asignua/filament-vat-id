<?php

declare(strict_types=1);

namespace Workbench\App\Livewire;

use Asignua\FilamentVatId\Enums\TaxIdType;
use Asignua\FilamentVatId\Forms\Components\TaxIdInput;
use Filament\Actions\Concerns\InteractsWithActions;
use Filament\Actions\Contracts\HasActions;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Concerns\InteractsWithSchemas;
use Filament\Schemas\Contracts\HasSchemas;
use Filament\Schemas\Schema;
use Livewire\Component;

class CompanyForm extends Component implements HasActions, HasSchemas
{
    use InteractsWithActions;
    use InteractsWithSchemas;

    /** @var array<string, mixed>|null */
    public ?array $data = [];

    public bool $remote = false;

    public string $type = 'eu_vat';

    public bool $withRepeater = false;

    /** @var array<string, mixed> */
    public array $saved = [];

    public function mount(): void
    {
        $this->form->fill();
    }

    public function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                Select::make('country')->options(['PL' => 'Poland', 'CZ' => 'Czechia', 'DE' => 'Germany', 'GR' => 'Greece', 'UA' => 'Ukraine']),
                TaxIdInput::make('tax_id')
                    ->type(TaxIdType::from($this->type))
                    ->countryField('country')
                    ->vies($this->remote)
                    ->lookup()
                    ->fill(['name' => 'company_name', 'address' => 'address', 'bankAccounts.0' => 'iban', 'regon' => 'regon']),
                TextInput::make('company_name'),
                TextInput::make('address'),
                TextInput::make('iban'),
                TextInput::make('regon'),
                Repeater::make('rows')
                    ->required()
                    ->visible($this->withRepeater)
                    ->schema([TextInput::make('v')->required()]),
            ])
            ->statePath('data');
    }

    public function save(): void
    {
        $this->saved = $this->form->getState();
    }

    public function render(): string
    {
        return <<<'HTML'
        <div>
            <form wire:submit="save">{{ $this->form }}</form>
            <x-filament-actions::modals />
        </div>
        HTML;
    }
}
