<?php

namespace App\Filament\Admin\Resources\RoutingRules\Schemas;

use App\Models\TrelloLabel;
use App\Models\TrelloList;
use App\Models\TrelloMember;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

class RoutingRuleForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Основное')
                    ->columns(2)
                    ->schema([
                        TextInput::make('name')
                            ->label('Название правила')
                            ->required()
                            ->columnSpanFull(),
                        TextInput::make('priority')
                            ->label('Приоритет')
                            ->numeric()
                            ->default(0)
                            ->helperText('Больше = выше приоритет'),
                        Toggle::make('is_active')
                            ->label('Активно')
                            ->default(true)
                            ->inline(false),
                    ]),

                Section::make('Условия срабатывания')
                    ->columns(2)
                    ->description('Все поля опциональны. Пустое поле = любое значение.')
                    ->schema([
                        TextInput::make('telegram_chat_id')
                            ->label('Telegram Chat ID')
                            ->numeric()
                            ->default(null),
                        Select::make('chat_type')
                            ->label('Тип чата')
                            ->options([
                                'private' => 'Личный (private)',
                                'group' => 'Группа (group)',
                                'supergroup' => 'Супергруппа (supergroup)',
                            ])
                            ->placeholder('Любой тип')
                            ->default(null),
                        TextInput::make('command')
                            ->label('Команда')
                            ->placeholder('/task')
                            ->default(null),
                        TextInput::make('keyword')
                            ->label('Ключевое слово')
                            ->default(null),
                        Select::make('has_photo')
                            ->label('Наличие фото')
                            ->options([
                                '1' => 'Только с фото',
                                '0' => 'Только без фото',
                            ])
                            ->placeholder('Не важно (любое)')
                            ->default(null),
                        Select::make('is_forwarded')
                            ->label('Пересланное сообщение')
                            ->options([
                                '1' => 'Только пересланные',
                                '0' => 'Только прямые',
                            ])
                            ->placeholder('Не важно (любое)')
                            ->default(null),
                    ]),

                Section::make('Действие')
                    ->columns(2)
                    ->schema([
                        Select::make('target_list_id')
                            ->label('Целевой список Trello')
                            ->options(
                                TrelloList::where('is_active', true)
                                    ->with('connection')
                                    ->get()
                                    ->mapWithKeys(fn (TrelloList $list) => [
                                        $list->id => "{$list->name} [{$list->connection->name}]",
                                    ])
                            )
                            ->searchable()
                            ->required()
                            ->columnSpanFull(),
                        Select::make('label_ids')
                            ->label('Метки Trello')
                            ->multiple()
                            ->options(
                                TrelloLabel::where('is_active', true)
                                    ->get()
                                    ->mapWithKeys(fn (TrelloLabel $label) => [
                                        $label->trello_label_id => $label->name
                                            ? "{$label->name} ({$label->color})"
                                            : $label->color,
                                    ])
                            )
                            ->default([]),
                        Select::make('member_ids')
                            ->label('Участники Trello')
                            ->multiple()
                            ->options(
                                TrelloMember::where('is_active', true)
                                    ->get()
                                    ->mapWithKeys(fn (TrelloMember $member) => [
                                        $member->trello_member_id => "{$member->full_name} (@{$member->username})",
                                    ])
                            )
                            ->default([]),
                    ]),

                Section::make('Шаблоны карточки')
                    ->schema([
                        TextInput::make('card_title_template')
                            ->label('Заголовок карточки')
                            ->required()
                            ->helperText('Переменные: {{first_name}}, {{username}}, {{text_preview}}, {{date}}, {{time}}')
                            ->columnSpanFull(),
                        Textarea::make('card_description_template')
                            ->label('Описание карточки')
                            ->required()
                            ->rows(6)
                            ->helperText('Переменные: {{first_name}}, {{username}}, {{user_id}}, {{text}}, {{text_preview}}, {{date}}, {{time}}, {{chat_type}}')
                            ->columnSpanFull(),
                    ]),
            ]);
    }
}
