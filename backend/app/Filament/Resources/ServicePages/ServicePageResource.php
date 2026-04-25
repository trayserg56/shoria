<?php

namespace App\Filament\Resources\ServicePages;

use App\Filament\Resources\ServicePages\Pages\CreateServicePage;
use App\Filament\Resources\ServicePages\Pages\EditServicePage;
use App\Filament\Resources\ServicePages\Pages\ListServicePages;
use App\Filament\Resources\ServicePages\Schemas\ServicePageForm;
use App\Filament\Resources\ServicePages\Tables\ServicePagesTable;
use App\Models\ServicePage;
use App\Support\Admin\AdminAccess;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Model;
use UnitEnum;

class ServicePageResource extends Resource
{
    protected static ?string $model = ServicePage::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedDocumentText;

    protected static ?string $navigationLabel = 'Служебные страницы';

    protected static string|UnitEnum|null $navigationGroup = 'Контент';

    public static function canViewAny(): bool
    {
        return AdminAccess::canManageContentResource('service_pages');
    }

    public static function canCreate(): bool
    {
        return AdminAccess::canManageContentResource('service_pages');
    }

    public static function canEdit(Model $record): bool
    {
        return AdminAccess::canManageContentResource('service_pages');
    }

    public static function canDelete(Model $record): bool
    {
        return AdminAccess::canManageContentResource('service_pages');
    }

    public static function canDeleteAny(): bool
    {
        return AdminAccess::canManageContentResource('service_pages');
    }

    public static function form(Schema $schema): Schema
    {
        return ServicePageForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return ServicePagesTable::configure($table);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListServicePages::route('/'),
            'create' => CreateServicePage::route('/create'),
            'edit' => EditServicePage::route('/{record}/edit'),
        ];
    }
}
