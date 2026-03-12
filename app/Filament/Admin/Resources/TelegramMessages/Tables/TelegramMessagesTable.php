<?php

namespace App\Filament\Admin\Resources\TelegramMessages\Tables;

use Filament\Actions\ViewAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Table;

class TelegramMessagesTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('id')
                    ->label('#')
                    ->sortable(),
                TextColumn::make('first_name')
                    ->label('Имя')
                    ->searchable(),
                TextColumn::make('username')
                    ->label('Username')
                    ->placeholder('—')
                    ->searchable()
                    ->formatStateUsing(fn (?string $state) => $state ? "@{$state}" : null),
                TextColumn::make('chat_type')
                    ->label('Тип чата')
                    ->badge(),
                TextColumn::make('text')
                    ->label('Текст')
                    ->placeholder('—')
                    ->limit(80)
                    ->searchable(),
                TextColumn::make('processing_status')
                    ->label('Статус')
                    ->badge()
                    ->color(fn (string $state) => match ($state) {
                        'success' => 'success',
                        'skipped' => 'warning',
                        'failed' => 'danger',
                        default => 'gray',
                    })
                    ->formatStateUsing(fn (string $state) => match ($state) {
                        'pending' => 'Ожидает',
                        'skipped' => 'Пропущено',
                        'success' => 'Успех',
                        'failed' => 'Ошибка',
                        default => $state,
                    }),
                TextColumn::make('processing_notes')
                    ->label('Причина')
                    ->placeholder('—')
                    ->limit(50)
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('received_at')
                    ->label('Получено')
                    ->dateTime('d.m.Y H:i')
                    ->sortable(),
            ])
            ->defaultSort('received_at', 'desc')
            ->poll('5s')
            ->filters([
                SelectFilter::make('processing_status')
                    ->label('Статус')
                    ->options([
                        'pending' => 'Ожидает',
                        'skipped' => 'Пропущено',
                        'success' => 'Успех',
                        'failed' => 'Ошибка',
                    ]),
                TernaryFilter::make('processed')
                    ->label('Обработанные')
                    ->nullable()
                    ->attribute('processed_at'),
            ])
            ->recordActions([
                ViewAction::make(),
            ])
            ->toolbarActions([]);
    }
}
