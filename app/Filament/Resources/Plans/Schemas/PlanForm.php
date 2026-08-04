<?php

namespace App\Filament\Resources\Plans\Schemas;

use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

class PlanForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Название')
                    ->columns(2)
                    ->schema([
                        TextInput::make('name_ru')
                            ->label('Название (RU)')
                            ->required()
                            ->maxLength(120),
                        TextInput::make('name_kk')
                            ->label('Атауы (KK)')
                            ->maxLength(120),
                        TextInput::make('tagline_ru')
                            ->label('Подзаголовок (RU)')
                            ->maxLength(160),
                        TextInput::make('tagline_kk')
                            ->label('Тақырыпша (KK)')
                            ->maxLength(160),
                    ]),

                Section::make('Цена')
                    ->columns(3)
                    ->schema([
                        // Owner-facing price is in tenge; storage is in tiyn. Convert
                        // on the boundary so nothing downstream sees a fraction.
                        TextInput::make('price')
                            ->label('Цена, ₸')
                            ->numeric()
                            ->required()
                            ->minValue(0)
                            ->formatStateUsing(fn (?int $state): int => (int) round(((int) $state) / 100))
                            ->dehydrateStateUsing(fn (?string $state): int => (int) round(((float) $state) * 100)),
                        TextInput::make('discount_percent')
                            ->label('Скидка, %')
                            ->numeric()
                            ->default(0)
                            ->minValue(0)
                            ->maxValue(100)
                            ->suffix('%'),
                        Select::make('period')
                            ->label('Срок доступа')
                            ->options([
                                'month' => 'На месяц',
                                'halfyear' => 'На 6 месяцев',
                                'year' => 'На год',
                            ])
                            ->default('month')
                            ->required(),
                    ]),

                Section::make('Возможности')
                    ->schema([
                        Repeater::make('features')
                            ->label('Список фич')
                            ->schema([
                                TextInput::make('ru')->label('RU')->required(),
                                TextInput::make('kk')->label('KK'),
                            ])
                            ->columns(2)
                            ->addActionLabel('Добавить строку')
                            ->reorderable()
                            ->default([]),
                    ]),

                Section::make('Ограничения и показ')
                    ->columns(2)
                    ->schema([
                        TextInput::make('max_establishments')
                            ->label('Макс. заведений')
                            ->helperText('Пусто = без ограничения.')
                            ->numeric()
                            ->minValue(1),
                        // Content caps enforced on menu editing (free tier carries
                        // them; empty = unlimited).
                        TextInput::make('max_categories')
                            ->label('Макс. разделов в меню')
                            ->helperText('Пусто = без ограничения.')
                            ->numeric()
                            ->minValue(1),
                        TextInput::make('max_dishes_per_category')
                            ->label('Макс. блюд в разделе')
                            ->helperText('Пусто = без ограничения.')
                            ->numeric()
                            ->minValue(1),
                        TextInput::make('sort')
                            ->label('Порядок')
                            ->numeric()
                            ->default(0),
                        Toggle::make('is_active')
                            ->label('Активен (виден и доступен к заявке)')
                            ->default(true),
                        Toggle::make('is_featured')
                            ->label('Выделять как «Популярный»')
                            ->default(false),
                    ]),
            ]);
    }
}
