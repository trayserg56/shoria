<?php

namespace App\Filament\Resources\GiftCertificates;

use App\Filament\Resources\GiftCertificates\Pages\CreateGiftCertificate;
use App\Filament\Resources\GiftCertificates\Pages\EditGiftCertificate;
use App\Filament\Resources\GiftCertificates\Pages\ListGiftCertificates;
use App\Filament\Resources\GiftCertificates\Schemas\GiftCertificateForm;
use App\Filament\Resources\GiftCertificates\Tables\GiftCertificatesTable;
use App\Models\GiftCertificate;
use App\Support\Admin\AdminAccess;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Model;
use UnitEnum;

class GiftCertificateResource extends Resource
{
    protected static ?string $model = GiftCertificate::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedShoppingBag;

    protected static ?string $navigationLabel = 'Подарочные сертификаты';

    protected static ?string $modelLabel = 'сертификат';

    protected static ?string $pluralModelLabel = 'Подарочные сертификаты';

    protected static string|UnitEnum|null $navigationGroup = 'Маркетинг';

    protected static ?int $navigationSort = 15;

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
        return GiftCertificateForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return GiftCertificatesTable::configure($table);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListGiftCertificates::route('/'),
            'create' => CreateGiftCertificate::route('/create'),
            'edit' => EditGiftCertificate::route('/{record}/edit'),
        ];
    }
}
