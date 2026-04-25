<?php

namespace App\Filament\Resources\Brands;

use App\Filament\Resources\Brands\Pages\CreateBrand;
use App\Filament\Resources\Brands\Pages\EditBrand;
use App\Filament\Resources\Brands\Pages\ListBrands;
use App\Filament\Resources\Brands\Schemas\BrandForm;
use App\Filament\Resources\Brands\Tables\BrandsTable;
use App\Models\Brand;
use App\Support\Admin\AdminAccess;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Model;
use UnitEnum;

class BrandResource extends Resource
{
    protected static ?string $model = Brand::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedTag;

    protected static ?string $navigationLabel = 'Бренды';

    protected static string|UnitEnum|null $navigationGroup = 'Каталог';

    public static function canViewAny(): bool
    {
        return AdminAccess::canManageContentResource('brands');
    }

    public static function canCreate(): bool
    {
        return AdminAccess::canManageContentResource('brands');
    }

    public static function canEdit(Model $record): bool
    {
        return AdminAccess::canManageContentResource('brands');
    }

    public static function canDelete(Model $record): bool
    {
        return AdminAccess::canManageContentResource('brands');
    }

    public static function canDeleteAny(): bool
    {
        return AdminAccess::canManageContentResource('brands');
    }

    public static function form(Schema $schema): Schema
    {
        return BrandForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return BrandsTable::configure($table);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListBrands::route('/'),
            'create' => CreateBrand::route('/create'),
            'edit' => EditBrand::route('/{record}/edit'),
        ];
    }
}
