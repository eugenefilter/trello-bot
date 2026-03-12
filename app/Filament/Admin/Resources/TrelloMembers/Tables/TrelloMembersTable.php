<?php

namespace App\Filament\Admin\Resources\TrelloMembers\Tables;

use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
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
                    ->label('Имя')
                    ->searchable(),
                TextColumn::make('username')
                    ->label('Username')
                    ->searchable()
                    ->formatStateUsing(fn (string $state) => "@{$state}"),
                TextColumn::make('trello_member_id')
                    ->label('Trello ID')
                    ->toggleable(isToggledHiddenByDefault: true),
                IconColumn::make('is_active')
                    ->label('Активно')
                    ->boolean(),
                TextColumn::make('updated_at')
                    ->label('Обновлено')
                    ->dateTime('d.m.Y H:i')
                    ->sortable(),
            ])
            ->filters([
                TernaryFilter::make('is_active')->label('Активные'),
            ])
            ->recordActions([])
            ->toolbarActions([]);
    }
}
