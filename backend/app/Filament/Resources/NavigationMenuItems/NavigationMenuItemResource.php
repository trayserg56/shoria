<?php

namespace App\Filament\Resources\NavigationMenuItems;

use App\Filament\Resources\NavigationMenuItems\Pages\CreateNavigationMenuItem;
use App\Filament\Resources\NavigationMenuItems\Pages\EditNavigationMenuItem;
use App\Filament\Resources\NavigationMenuItems\Pages\ListNavigationMenuItems;
use App\Filament\Resources\NavigationMenuItems\Schemas\NavigationMenuItemForm;
use App\Filament\Resources\NavigationMenuItems\Tables\NavigationMenuItemsTable;
use App\Models\NavigationMenuItem;
use App\Support\Admin\AdminAccess;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Model;
use UnitEnum;

class NavigationMenuItemResource extends Resource
{
    protected static ?string $model = NavigationMenuItem::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedBars3BottomLeft;

    protected static ?string $navigationLabel = 'Меню сайта';

    protected static string|UnitEnum|null $navigationGroup = 'Контент';

    public static function canViewAny(): bool
    {
        return AdminAccess::canManageContentResource('navigation_menu_items');
    }

    public static function canCreate(): bool
    {
        return AdminAccess::canManageContentResource('navigation_menu_items');
    }

    public static function canEdit(Model $record): bool
    {
        return AdminAccess::canManageContentResource('navigation_menu_items');
    }

    public static function canDelete(Model $record): bool
    {
        return AdminAccess::canManageContentResource('navigation_menu_items');
    }

    public static function canDeleteAny(): bool
    {
        return AdminAccess::canManageContentResource('navigation_menu_items');
    }

    public static function form(Schema $schema): Schema
    {
        return NavigationMenuItemForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return NavigationMenuItemsTable::configure($table);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListNavigationMenuItems::route('/'),
            'create' => CreateNavigationMenuItem::route('/create'),
            'edit' => EditNavigationMenuItem::route('/{record}/edit'),
        ];
    }
}
