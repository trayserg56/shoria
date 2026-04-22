<?php

namespace App\Filament\Resources\DeliveryProviders;

use App\Filament\Resources\DeliveryProviders\Pages\CreateDeliveryProvider;
use App\Filament\Resources\DeliveryProviders\Pages\EditDeliveryProvider;
use App\Filament\Resources\DeliveryProviders\Pages\ListDeliveryProviders;
use App\Filament\Resources\DeliveryProviders\Schemas\DeliveryProviderForm;
use App\Filament\Resources\DeliveryProviders\Tables\DeliveryProvidersTable;
use App\Models\DeliveryProvider;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use UnitEnum;

class DeliveryProviderResource extends Resource
{
    protected static ?string $model = DeliveryProvider::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedTruck;

    protected static ?string $navigationLabel = 'Провайдеры доставки';

    protected static string|UnitEnum|null $navigationGroup = 'Интеграции';

    protected static ?int $navigationSort = 21;

    public static function form(Schema $schema): Schema
    {
        return DeliveryProviderForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return DeliveryProvidersTable::configure($table);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListDeliveryProviders::route('/'),
            'create' => CreateDeliveryProvider::route('/create'),
            'edit' => EditDeliveryProvider::route('/{record}/edit'),
        ];
    }
}
