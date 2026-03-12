<?php

namespace App\Filament\Admin\Resources\TrelloLabels\Tables;

use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Table;

class TrelloLabelsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('connection.name')
                    ->label('Подключение')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('name')
                    ->label('Название')
                    ->placeholder('—')
                    ->searchable(),
                TextColumn::make('color')
                    ->label('Цвет')
                    ->badge(),
                TextColumn::make('trello_label_id')
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
