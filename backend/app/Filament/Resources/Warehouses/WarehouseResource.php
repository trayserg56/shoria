<?php

namespace App\Filament\Resources\Warehouses;

use App\Filament\Resources\Warehouses\Pages\CreateWarehouse;
use App\Filament\Resources\Warehouses\Pages\EditWarehouse;
use App\Filament\Resources\Warehouses\Pages\ListWarehouses;
use App\Models\Warehouse;
use BackedEnum;
use App\Models\PriceType;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Actions\EditAction;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use UnitEnum;

class WarehouseResource extends Resource
{
    protected static ?string $model = Warehouse::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedBuildingStorefront;

    protected static ?string $navigationLabel = 'Склады';

    protected static string|UnitEnum|null $navigationGroup = 'Склад и цены';

    protected static ?int $navigationSort = 10;

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            TextInput::make('name')->label('Название')->required()->maxLength(255),
            TextInput::make('code')->label('Код')->required()->unique(ignoreRecord: true)->maxLength(64),
            TextInput::make('city')->label('Город')->maxLength(128),
            TextInput::make('region')->label('Регион')->maxLength(128),
            TextInput::make('address')->label('Адрес')->maxLength(255)->columnSpanFull(),
            TextInput::make('priority')->label('Приоритет')->numeric()->default(100),
            TextInput::make('external_id')->label('ID в 1С')->maxLength(255),
            Select::make('price_type_id')
                ->label('Тип цены склада')
                ->options(PriceType::query()->pluck('name', 'id'))
                ->placeholder('Розничная (по умолчанию)')
                ->nullable()
                ->searchable()
                ->helperText('Если не задан, используется розничный тип цены'),
            Toggle::make('is_active')->label('Активен')->default(true),
            Toggle::make('is_default')->label('Склад по умолчанию')->default(false),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('name')->label('Название')->searchable()->sortable(),
                TextColumn::make('code')->label('Код')->sortable(),
                TextColumn::make('city')->label('Город')->sortable(),
                TextColumn::make('priority')->label('Приоритет')->sortable(),
                IconColumn::make('is_default')->label('По умолчанию')->boolean(),
                IconColumn::make('is_active')->label('Активен')->boolean(),
                TextColumn::make('external_id')->label('ID 1С')->toggleable(isToggledHiddenByDefault: true),
            ])
            ->defaultSort('priority')
            ->actions([EditAction::make()]);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListWarehouses::route('/'),
            'create' => CreateWarehouse::route('/create'),
            'edit' => EditWarehouse::route('/{record}/edit'),
        ];
    }
}
