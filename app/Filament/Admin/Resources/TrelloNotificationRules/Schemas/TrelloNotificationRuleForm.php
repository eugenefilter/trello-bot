<?php

declare(strict_types=1);

namespace App\Filament\Admin\Resources\TrelloNotificationRules\Schemas;

use App\Models\TrelloConnection;
use App\Models\TrelloLabel;
use App\Models\TrelloList;
use App\Models\TrelloMember;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Schema;

class TrelloNotificationRuleForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema->components([
            Section::make('Основное')
                ->columns(2)
                ->schema([
                    TextInput::make('name')
                        ->label('Название правила')
                        ->required()
                        ->maxLength(255)
                        ->columnSpanFull(),
                    Select::make('connection_id')
                        ->label('Подключение')
                        ->options(TrelloConnection::pluck('name', 'id'))
                        ->required()
                        ->live(),
                    Toggle::make('is_active')
                        ->label('Активно')
                        ->default(true)
                        ->inline(false),
                ]),

            Section::make('Условие срабатывания')
                ->description('Правило сработает при перемещении карточки в указанный список')
                ->schema([
                    Select::make('trigger_list_id')
                        ->label('Целевой список (колонка)')
                        ->options(fn (Get $get) => TrelloList::query()
                            ->where('is_active', true)
                            ->when($get('connection_id'), fn ($q, $id) => $q->where('connection_id', $id))
                            ->get()
                            ->mapWithKeys(fn (TrelloList $list) => [
                                $list->trello_list_id => $list->name,
                            ])
                        )
                        ->searchable()
                        ->required()
                        ->columnSpanFull(),
                ]),

            Section::make('Фильтры карточки')
                ->description('Опционально. Оставьте пустым — срабатывает для любой карточки в указанном списке.')
                ->columns(2)
                ->schema([
                    Select::make('filter_label_ids')
                        ->label('Метки')
                        ->multiple()
                        ->options(fn (Get $get) => TrelloLabel::query()
                            ->where('is_active', true)
                            ->when($get('connection_id'), fn ($q, $id) => $q->where('connection_id', $id))
                            ->get()
                            ->mapWithKeys(fn (TrelloLabel $label) => [
                                $label->trello_label_id => $label->name
                                    ? "{$label->name} ({$label->color})"
                                    : $label->color,
                            ])
                        )
                        ->default(null)
                        ->placeholder('Любые метки'),
                    Select::make('filter_label_mode')
                        ->label('Режим меток')
                        ->options([
                            'any' => 'Хотя бы одна (any)',
                            'all' => 'Все метки (all)',
                        ])
                        ->default('any')
                        ->required(),
                    Select::make('filter_member_binding_ids')
                        ->label('Участники карточки')
                        ->multiple()
                        ->options(fn (Get $get) => TrelloMember::query()
                            ->where('is_active', true)
                            ->when($get('connection_id'), fn ($q, $id) => $q->where('connection_id', $id))
                            ->get()
                            ->mapWithKeys(fn (TrelloMember $member) => [
                                $member->id => "{$member->full_name} (@{$member->username})",
                            ])
                        )
                        ->default(null)
                        ->placeholder('Любые участники')
                        ->columnSpanFull(),
                ]),

            Section::make('Telegram-уведомление')
                ->columns(1)
                ->schema([
                    TextInput::make('telegram_chat_id')
                        ->label('Telegram Chat ID')
                        ->required()
                        ->placeholder('-1001234567890'),
                    Select::make('mention_member_binding_ids')
                        ->label('Упомянуть в сообщении')
                        ->multiple()
                        ->options(fn (Get $get) => TrelloMember::query()
                            ->where('is_active', true)
                            ->when($get('connection_id'), fn ($q, $id) => $q->where('connection_id', $id))
                            ->get()
                            ->mapWithKeys(fn (TrelloMember $member) => [
                                $member->id => "{$member->full_name} (@{$member->username})",
                            ])
                        )
                        ->default(null)
                        ->placeholder('Никого не упоминать')
                        ->helperText('Упоминание сработает только если участник привязан к Telegram (@username)'),
                    Textarea::make('message_template')
                        ->label('Шаблон сообщения')
                        ->required()
                        ->rows(4)
                        ->default('Карточка {{card_name}} перемещена в {{list_name}} {{mentions}}\n{{card_url}}')
                        ->helperText('Переменные: {{card_name}}, {{card_url}}, {{list_name}}, {{board_name}}, {{mentions}}'),
                ]),
        ]);
    }
}
