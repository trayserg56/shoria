<?php

namespace App\Filament\Resources\GiftCertificates\Tables;

use App\Models\GiftCertificate;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class GiftCertificatesTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('code')
                    ->label('Код')
                    ->searchable()
                    ->sortable()
                    ->copyable(),
                Tables\Columns\TextColumn::make('initial_amount')
                    ->label('Номинал')
                    ->formatStateUsing(fn ($state) => number_format((float) $state, 0, '.', ' ').' ₽')
                    ->sortable(),
                Tables\Columns\TextColumn::make('balance_remaining')
                    ->label('Остаток')
                    ->formatStateUsing(fn ($state) => number_format((float) $state, 0, '.', ' ').' ₽')
                    ->sortable(),
                Tables\Columns\TextColumn::make('status')
                    ->label('Статус')
                    ->badge()
                    ->formatStateUsing(fn (string $state) => match ($state) {
                        GiftCertificate::STATUS_ACTIVE => 'Активен',
                        GiftCertificate::STATUS_DEPLETED => 'Исчерпан',
                        GiftCertificate::STATUS_CANCELLED => 'Аннулирован',
                        default => $state,
                    })
                    ->color(fn (string $state) => match ($state) {
                        GiftCertificate::STATUS_ACTIVE => 'success',
                        GiftCertificate::STATUS_DEPLETED => 'gray',
                        GiftCertificate::STATUS_CANCELLED => 'danger',
                        default => 'gray',
                    }),
                Tables\Columns\TextColumn::make('expires_at')
                    ->label('До')
                    ->dateTime('d.m.Y H:i')
                    ->placeholder('Без срока')
                    ->sortable(),
                Tables\Columns\TextColumn::make('recipient_email')
                    ->label('Получатель')
                    ->placeholder('—')
                    ->toggleable(),
                Tables\Columns\TextColumn::make('owner.email')
                    ->label('Владелец (ЛК)')
                    ->placeholder('—')
                    ->toggleable(),
                Tables\Columns\TextColumn::make('created_at')
                    ->label('Создан')
                    ->dateTime('d.m.Y H:i')
                    ->sortable(),
            ])
            ->filters([
                Tables\Filters\Filter::make('hide_fully_used')
                    ->label('Скрывать исчерпанные и нулевой остаток')
                    ->toggle()
                    ->default(true)
                    ->query(function (Builder $query, array $data): void {
                        $query->where(function (Builder $inner): void {
                            $inner->where('gift_certificates.status', '!=', GiftCertificate::STATUS_DEPLETED)
                                ->where('gift_certificates.balance_remaining', '>', 0);
                        });
                    }),
                Tables\Filters\SelectFilter::make('status')
                    ->label('Статус')
                    ->options([
                        GiftCertificate::STATUS_ACTIVE => 'Активен',
                        GiftCertificate::STATUS_DEPLETED => 'Исчерпан',
                        GiftCertificate::STATUS_CANCELLED => 'Аннулирован',
                    ]),
            ])
            ->recordActions([
                EditAction::make(),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
            ])
            ->defaultSort('id', 'desc');
    }
}
