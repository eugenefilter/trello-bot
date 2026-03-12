<?php

namespace App\Filament\Admin\Resources\TrelloCardLogs\Tables;

use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;

class TrelloCardLogsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('id')
                    ->label('#')
                    ->sortable(),
                TextColumn::make('status')
                    ->label('Статус')
                    ->badge()
                    ->color(fn (string $state) => match ($state) {
                        'success' => 'success',
                        'error' => 'danger',
                        default => 'gray',
                    }),
                TextColumn::make('telegramMessage.text')
                    ->label('Сообщение')
                    ->limit(60)
                    ->placeholder('—'),
                TextColumn::make('trello_list_id')
                    ->label('Список Trello')
                    ->placeholder('—'),
                TextColumn::make('trello_card_url')
                    ->label('Ссылка на карточку')
                    ->url(fn ($record) => $record->trello_card_url)
                    ->openUrlInNewTab()
                    ->placeholder('—')
                    ->limit(40),
                TextColumn::make('error_message')
                    ->label('Ошибка')
                    ->placeholder('—')
                    ->limit(60)
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('created_at')
                    ->label('Создано')
                    ->dateTime('d.m.Y H:i')
                    ->sortable(),
            ])
            ->defaultSort('created_at', 'desc')
            ->filters([
                SelectFilter::make('status')
                    ->label('Статус')
                    ->options([
                        'success' => 'Успех',
                        'error' => 'Ошибка',
                    ]),
            ])
            ->recordActions([])
            ->toolbarActions([]);
    }
}
