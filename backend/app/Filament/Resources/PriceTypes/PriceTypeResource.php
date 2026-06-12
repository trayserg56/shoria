<?php

namespace App\Filament\Resources\PriceTypes;

use App\Filament\Resources\PriceTypes\Pages\CreatePriceType;
use App\Filament\Resources\PriceTypes\Pages\EditPriceType;
use App\Filament\Resources\PriceTypes\Pages\ListPriceTypes;
use App\Models\PriceType;
use BackedEnum;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Actions\EditAction;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use UnitEnum;

class PriceTypeResource extends Resource
{
    protected static ?string $model = PriceType::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedTag;

    protected static ?string $navigationLabel = 'Типы цен';

    protected static string|UnitEnum|null $navigationGroup = 'Склад и цены';

    protected static ?int $navigationSort = 20;

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            TextInput::make('name')->label('Название')->required()->maxLength(255),
            TextInput::make('code')->label('Код')->required()->unique(ignoreRecord: true)->maxLength(64),
            TextInput::make('external_id')->label('ID в 1С')->maxLength(255)->nullable(),
            Toggle::make('is_default')->label('По умолчанию')->default(false),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('name')->label('Название')->searchable()->sortable(),
                TextColumn::make('code')->label('Код')->sortable(),
                IconColumn::make('is_default')->label('По умолчанию')->boolean(),
                TextColumn::make('external_id')->label('ID 1С')->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('updated_at')->label('Обновлён')->dateTime('d.m.Y')->sortable()->toggleable(isToggledHiddenByDefault: true),
            ])
            ->defaultSort('name')
            ->actions([EditAction::make()]);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListPriceTypes::route('/'),
            'create' => CreatePriceType::route('/create'),
            'edit' => EditPriceType::route('/{record}/edit'),
        ];
    }
}
