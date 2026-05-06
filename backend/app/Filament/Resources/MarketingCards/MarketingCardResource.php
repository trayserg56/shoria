<?php

namespace App\Filament\Resources\MarketingCards;

use App\Filament\Resources\MarketingCards\Pages\CreateMarketingCard;
use App\Filament\Resources\MarketingCards\Pages\EditMarketingCard;
use App\Filament\Resources\MarketingCards\Pages\ListMarketingCards;
use App\Filament\Resources\MarketingCards\Schemas\MarketingCardForm;
use App\Filament\Resources\MarketingCards\Tables\MarketingCardsTable;
use App\Models\MarketingCard;
use App\Support\Admin\AdminAccess;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Model;
use UnitEnum;

class MarketingCardResource extends Resource
{
    protected static ?string $model = MarketingCard::class;

    protected static ?string $navigationLabel = 'Маркетинговый блок';

    protected static ?string $modelLabel = 'Карточка';

    protected static ?string $pluralModelLabel = 'Карточки';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedPhoto;

    protected static string|UnitEnum|null $navigationGroup = 'Контент';

    protected static ?int $navigationSort = 12;

    public static function canViewAny(): bool
    {
        return AdminAccess::canManageContentResource('marketing_cards');
    }

    public static function canCreate(): bool
    {
        return AdminAccess::canManageContentResource('marketing_cards');
    }

    public static function canEdit(Model $record): bool
    {
        return AdminAccess::canManageContentResource('marketing_cards');
    }

    public static function canDelete(Model $record): bool
    {
        return AdminAccess::canManageContentResource('marketing_cards');
    }

    public static function canDeleteAny(): bool
    {
        return AdminAccess::canManageContentResource('marketing_cards');
    }

    public static function form(Schema $schema): Schema
    {
        return MarketingCardForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return MarketingCardsTable::configure($table);
    }

    public static function getRelations(): array
    {
        return [
            //
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => ListMarketingCards::route('/'),
            'create' => CreateMarketingCard::route('/create'),
            'edit' => EditMarketingCard::route('/{record}/edit'),
        ];
    }
}
