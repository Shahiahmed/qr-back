<?php

namespace App\Filament\Resources\Establishments\Schemas;

use App\Models\Establishment;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Schema;

class EstablishmentForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                // Owner is not reassignable from moderation — showing it, disabled,
                // keeps the field out of the payload while staying visible.
                Select::make('user_id')
                    ->label('Владелец')
                    ->relationship('user', 'name')
                    ->disabled()
                    ->dehydrated(false),
                TextInput::make('name')
                    ->label('Название')
                    ->required()
                    ->maxLength(255),
                TextInput::make('slug')
                    ->label('Адрес меню (slug)')
                    ->required()
                    ->maxLength(255)
                    ->helperText('Меняет публичный URL /m/{slug}. Старый адрес перестанет работать.'),
                Select::make('currency')
                    ->label('Валюта')
                    ->options(array_combine(Establishment::CURRENCIES, Establishment::CURRENCIES))
                    ->required(),
                Select::make('default_locale')
                    ->label('Язык по умолчанию')
                    ->options(['ru' => 'Русский', 'kk' => 'Қазақша'])
                    ->required(),
                Select::make('theme')
                    ->label('Тема оформления')
                    ->options(array_combine(Establishment::THEMES, Establishment::THEMES))
                    ->required(),
                TextInput::make('address')
                    ->label('Адрес')
                    ->maxLength(255),
                TextInput::make('phone')
                    ->label('Телефон')
                    ->tel()
                    ->maxLength(255),
            ]);
    }
}
