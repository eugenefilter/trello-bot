<?php

declare(strict_types=1);

namespace App\Filament\Admin\Resources\TrelloMembers\Tables;

use Filament\Actions\EditAction;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Table;

class TrelloMembersTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('connection.name')
                    ->label('Подключение')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('full_name')
                    ->label('Имя в Trello')
                    ->searchable(),
                TextColumn::make('username')
                    ->label('Trello @username')
                    ->searchable()
                    ->formatStateUsing(fn (string $state) => "@{$state}"),
                TextColumn::make('binding.telegram_full_name')
                    ->label('Имя в Telegram')
                    ->placeholder('—')
                    ->searchable(),
                TextColumn::make('binding.telegram_username')
                    ->label('Telegram @username')
                    ->placeholder('—')
                    ->formatStateUsing(fn (?string $state) => $state ? "@{$state}" : null),
                TextColumn::make('binding.telegram_user_id')
                    ->label('Telegram ID')
                    ->placeholder('—')
                    ->toggleable(isToggledHiddenByDefault: true),
                IconColumn::make('binding.id')
                    ->label('Привязан')
                    ->boolean()
                    ->trueIcon('heroicon-o-check-circle')
                    ->falseIcon('heroicon-o-x-circle')
                    ->trueColor('success')
                    ->falseColor('gray'),
                IconColumn::make('is_active')
                    ->label('Активно')
                    ->boolean()
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('updated_at')
                    ->label('Обновлено')
                    ->dateTime('d.m.Y H:i')
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->modifyQueryUsing(fn ($query) => $query->with('binding'))
            ->defaultSort('full_name')
            ->filters([
                SelectFilter::make('connection_id')
                    ->relationship('connection', 'name')
                    ->label('Подключение'),
                TernaryFilter::make('is_active')->label('Активные'),
            ])
            ->recordActions([
                EditAction::make()->label('Привязать'),
            ])
            ->toolbarActions([]);
    }
}
