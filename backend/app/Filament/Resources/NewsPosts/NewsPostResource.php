<?php

namespace App\Filament\Resources\NewsPosts;

use App\Filament\Resources\NewsPosts\Pages\CreateNewsPost;
use App\Filament\Resources\NewsPosts\Pages\EditNewsPost;
use App\Filament\Resources\NewsPosts\Pages\ListNewsPosts;
use App\Filament\Resources\NewsPosts\Schemas\NewsPostForm;
use App\Filament\Resources\NewsPosts\Tables\NewsPostsTable;
use App\Models\NewsPost;
use App\Support\Admin\AdminAccess;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Model;

class NewsPostResource extends Resource
{
    protected static ?string $model = NewsPost::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedRectangleStack;

    public static function canViewAny(): bool
    {
        return AdminAccess::canManageContentResource('news_posts');
    }

    public static function canCreate(): bool
    {
        return AdminAccess::canManageContentResource('news_posts');
    }

    public static function canEdit(Model $record): bool
    {
        return AdminAccess::canManageContentResource('news_posts');
    }

    public static function canDelete(Model $record): bool
    {
        return AdminAccess::canManageContentResource('news_posts');
    }

    public static function canDeleteAny(): bool
    {
        return AdminAccess::canManageContentResource('news_posts');
    }

    public static function form(Schema $schema): Schema
    {
        return NewsPostForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return NewsPostsTable::configure($table);
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
            'index' => ListNewsPosts::route('/'),
            'create' => CreateNewsPost::route('/create'),
            'edit' => EditNewsPost::route('/{record}/edit'),
        ];
    }
}
