<?php

namespace App\Filament\Resources\DeliveryMethods;

use App\Filament\Resources\DeliveryMethods\Pages\CreateDeliveryMethod;
use App\Filament\Resources\DeliveryMethods\Pages\EditDeliveryMethod;
use App\Filament\Resources\DeliveryMethods\Pages\ListDeliveryMethods;
use App\Filament\Resources\DeliveryMethods\Schemas\DeliveryMethodForm;
use App\Filament\Resources\DeliveryMethods\Tables\DeliveryMethodsTable;
use App\Models\DeliveryMethod;
use App\Support\Admin\AdminAccess;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Model;

class DeliveryMethodResource extends Resource
{
    protected static ?string $model = DeliveryMethod::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedRectangleStack;

    protected static ?string $navigationLabel = 'Доставка';

    public static function canViewAny(): bool
    {
        return AdminAccess::canUseAdminOnlyResource();
    }

    public static function canCreate(): bool
    {
        return AdminAccess::canUseAdminOnlyResource();
    }

    public static function canEdit(Model $record): bool
    {
        return AdminAccess::canUseAdminOnlyResource();
    }

    public static function canDelete(Model $record): bool
    {
        return AdminAccess::canUseAdminOnlyResource();
    }

    public static function canDeleteAny(): bool
    {
        return AdminAccess::canUseAdminOnlyResource();
    }

    public static function form(Schema $schema): Schema
    {
        return DeliveryMethodForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return DeliveryMethodsTable::configure($table);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListDeliveryMethods::route('/'),
            'create' => CreateDeliveryMethod::route('/create'),
            'edit' => EditDeliveryMethod::route('/{record}/edit'),
        ];
    }
}
