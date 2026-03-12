<?php

namespace App\Filament\Admin\Resources\TelegramMessages\Tables;

use Filament\Actions\ViewAction;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
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
                IconColumn::make('processed_at')
                    ->label('Обработано')
                    ->boolean()
                    ->getStateUsing(fn ($record) => $record->processed_at !== null),
                TextColumn::make('received_at')
                    ->label('Получено')
                    ->dateTime('d.m.Y H:i')
                    ->sortable(),
            ])
            ->defaultSort('received_at', 'desc')
            ->filters([
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
