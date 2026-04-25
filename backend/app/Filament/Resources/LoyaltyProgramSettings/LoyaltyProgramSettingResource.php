<?php

namespace App\Filament\Resources\LoyaltyProgramSettings;

use App\Filament\Resources\LoyaltyProgramSettings\Pages\CreateLoyaltyProgramSetting;
use App\Filament\Resources\LoyaltyProgramSettings\Pages\EditLoyaltyProgramSetting;
use App\Filament\Resources\LoyaltyProgramSettings\Pages\ListLoyaltyProgramSettings;
use App\Filament\Resources\LoyaltyProgramSettings\Schemas\LoyaltyProgramSettingForm;
use App\Filament\Resources\LoyaltyProgramSettings\Tables\LoyaltyProgramSettingsTable;
use App\Models\LoyaltyProgramSetting;
use App\Support\Admin\AdminAccess;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Model;
use UnitEnum;

class LoyaltyProgramSettingResource extends Resource
{
    protected static ?string $model = LoyaltyProgramSetting::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedSparkles;

    protected static ?string $navigationLabel = 'Программа лояльности';

    protected static string|UnitEnum|null $navigationGroup = 'Настройки';

    protected static ?int $navigationSort = 30;

    public static function canViewAny(): bool
    {
        return AdminAccess::canUseAdminOnlyResource();
    }

    public static function canCreate(): bool
    {
        return AdminAccess::canUseAdminOnlyResource()
            && LoyaltyProgramSetting::query()->count() === 0;
    }

    public static function canEdit(Model $record): bool
    {
        return AdminAccess::canUseAdminOnlyResource();
    }

    public static function canDelete(Model $record): bool
    {
        return false;
    }

    public static function canDeleteAny(): bool
    {
        return false;
    }

    public static function form(Schema $schema): Schema
    {
        return LoyaltyProgramSettingForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return LoyaltyProgramSettingsTable::configure($table);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListLoyaltyProgramSettings::route('/'),
            'create' => CreateLoyaltyProgramSetting::route('/create'),
            'edit' => EditLoyaltyProgramSetting::route('/{record}/edit'),
        ];
    }
}
