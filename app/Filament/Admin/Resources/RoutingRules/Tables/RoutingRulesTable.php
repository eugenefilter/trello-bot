<?php

namespace App\Filament\Admin\Resources\RoutingRules\Tables;

use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Table;

class RoutingRulesTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('priority')
                    ->label('Приоритет')
                    ->numeric()
                    ->sortable(),
                TextColumn::make('name')
                    ->label('Название')
                    ->searchable(),
                TextColumn::make('telegram_chat_id')
                    ->label('Chat ID')
                    ->placeholder('—')
                    ->numeric(),
                TextColumn::make('chat_type')
                    ->label('Тип чата')
                    ->placeholder('—'),
                TextColumn::make('command')
                    ->label('Команда')
                    ->placeholder('—'),
                IconColumn::make('has_photo')
                    ->label('Фото')
                    ->boolean()
                    ->trueIcon('heroicon-o-photo')
                    ->falseIcon('heroicon-o-minus'),
                TextColumn::make('targetList.name')
                    ->label('Список Trello')
                    ->searchable(),
                IconColumn::make('is_active')
                    ->label('Активно')
                    ->boolean(),
                TextColumn::make('updated_at')
                    ->label('Обновлено')
                    ->dateTime('d.m.Y H:i')
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->defaultSort('priority', 'desc')
            ->filters([
                TernaryFilter::make('is_active')->label('Активные'),
            ])
            ->recordActions([
                EditAction::make(),
                DeleteAction::make(),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
            ]);
    }
}
