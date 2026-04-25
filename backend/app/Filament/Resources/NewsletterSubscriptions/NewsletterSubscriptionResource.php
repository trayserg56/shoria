<?php

namespace App\Filament\Resources\NewsletterSubscriptions;

use App\Filament\Resources\NewsletterSubscriptions\Pages\EditNewsletterSubscription;
use App\Filament\Resources\NewsletterSubscriptions\Pages\ListNewsletterSubscriptions;
use App\Filament\Resources\NewsletterSubscriptions\Schemas\NewsletterSubscriptionForm;
use App\Filament\Resources\NewsletterSubscriptions\Tables\NewsletterSubscriptionsTable;
use App\Models\NewsletterSubscription;
use App\Support\Admin\AdminAccess;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Model;
use UnitEnum;

class NewsletterSubscriptionResource extends Resource
{
    protected static ?string $model = NewsletterSubscription::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedRectangleStack;

    protected static ?string $navigationLabel = 'Подписки';

    protected static string|UnitEnum|null $navigationGroup = 'Настройки';

    public static function canViewAny(): bool
    {
        return AdminAccess::canUseAdminOnlyResource();
    }

    public static function canCreate(): bool
    {
        return false;
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
        return NewsletterSubscriptionForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return NewsletterSubscriptionsTable::configure($table);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListNewsletterSubscriptions::route('/'),
            'edit' => EditNewsletterSubscription::route('/{record}/edit'),
        ];
    }
}
