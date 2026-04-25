<?php

namespace App\Filament\Resources\LoyaltyProgramSettings\Schemas;

use App\Models\LoyaltyProgramSetting;
use Filament\Forms;
use Filament\Schemas\Components\Tabs;
use Filament\Schemas\Components\Tabs\Tab;
use Filament\Schemas\Schema;

class LoyaltyProgramSettingForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Tabs::make('Программа лояльности')
                    ->tabs([
                        Tab::make('Основное')
                            ->schema([
                                Forms\Components\Toggle::make('is_enabled')
                                    ->label('Программа включена')
                                    ->default(false)
                                    ->helperText('Можно быстро отключить программу без удаления правил и уровней.')
                                    ->columnSpanFull(),
                                Forms\Components\TextInput::make('base_accrual_percent')
                                    ->label('Базовое начисление (%)')
                                    ->numeric()
                                    ->required()
                                    ->minValue(0)
                                    ->maxValue(100)
                                    ->default(5),
                                Forms\Components\TextInput::make('max_redeem_percent')
                                    ->label('Максимум списания от заказа (%)')
                                    ->numeric()
                                    ->required()
                                    ->minValue(0)
                                    ->maxValue(100)
                                    ->default(25),
                                Forms\Components\TextInput::make('point_value')
                                    ->label('Стоимость 1 балла (₽)')
                                    ->numeric()
                                    ->required()
                                    ->minValue(0.01)
                                    ->default(1),
                                Forms\Components\TextInput::make('min_order_total_for_redeem')
                                    ->label('Мин. сумма заказа для списания (₽)')
                                    ->numeric()
                                    ->required()
                                    ->minValue(0)
                                    ->default(0),
                            ])
                            ->columns(2),
                        Tab::make('Уровни')
                            ->schema([
                                Forms\Components\Repeater::make('tiers')
                                    ->label('Уровни программы')
                                    ->default([
                                        ['name' => 'Base', 'min_spent' => 0, 'accrual_percent' => 5],
                                        ['name' => 'Silver', 'min_spent' => 30000, 'accrual_percent' => 6],
                                        ['name' => 'Gold', 'min_spent' => 80000, 'accrual_percent' => 7],
                                        ['name' => 'Platinum', 'min_spent' => 150000, 'accrual_percent' => 8],
                                    ])
                                    ->reorderableWithButtons()
                                    ->collapsed()
                                    ->addActionLabel('Добавить уровень')
                                    ->itemLabel(fn (array $state): ?string => $state['name'] ?? null)
                                    ->schema([
                                        Forms\Components\TextInput::make('name')
                                            ->label('Название уровня')
                                            ->required()
                                            ->maxLength(120),
                                        Forms\Components\TextInput::make('min_spent')
                                            ->label('Порог суммы покупок (₽)')
                                            ->required()
                                            ->numeric()
                                            ->minValue(0),
                                        Forms\Components\TextInput::make('accrual_percent')
                                            ->label('Начисление (%)')
                                            ->required()
                                            ->numeric()
                                            ->minValue(0)
                                            ->maxValue(100),
                                    ])
                                    ->columns(3)
                                    ->columnSpanFull(),
                            ]),
                        Tab::make('Публичные условия')
                            ->schema([
                                Forms\Components\RichEditor::make('terms_content')
                                    ->label('Условия программы')
                                    ->fileAttachmentsDisk('public')
                                    ->fileAttachmentsDirectory('loyalty-terms')
                                    ->fileAttachmentsVisibility('public')
                                    ->fileAttachmentsMaxSize(2048)
                                    ->helperText('Этот текст будет показан на публичной странице программы лояльности.')
                                    ->columnSpanFull(),
                            ]),
                        Tab::make('Служебное')
                            ->schema([
                                Forms\Components\Placeholder::make('created_by_info')
                                    ->label('Создал')
                                    ->content(fn (?LoyaltyProgramSetting $record): string => $record?->createdBy?->email ?? '—'),
                                Forms\Components\Placeholder::make('updated_by_info')
                                    ->label('Изменил')
                                    ->content(fn (?LoyaltyProgramSetting $record): string => $record?->updatedBy?->email ?? '—'),
                                Forms\Components\Placeholder::make('created_at_info')
                                    ->label('Дата создания')
                                    ->content(fn (?LoyaltyProgramSetting $record): string => $record?->created_at?->format('d.m.Y H:i') ?? '—'),
                                Forms\Components\Placeholder::make('updated_at_info')
                                    ->label('Дата изменения')
                                    ->content(fn (?LoyaltyProgramSetting $record): string => $record?->updated_at?->format('d.m.Y H:i') ?? '—'),
                            ])
                            ->columns(2)
                            ->visible(fn (?LoyaltyProgramSetting $record): bool => $record !== null),
                    ])
                    ->columnSpanFull(),
            ])
            ->columns(2);
    }
}
