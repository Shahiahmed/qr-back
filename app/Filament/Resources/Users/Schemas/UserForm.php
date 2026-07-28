<?php

namespace App\Filament\Resources\Users\Schemas;

use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Schema;
use Illuminate\Support\Facades\Hash;

class UserForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextInput::make('name')
                    ->label('Имя')
                    ->required()
                    ->maxLength(255),
                TextInput::make('email')
                    ->label('Email')
                    ->email()
                    ->required()
                    ->maxLength(255)
                    // Same rule as registration — a case-variant must not slip a duplicate in.
                    ->dehydrateStateUsing(fn (string $state): string => mb_strtolower($state))
                    ->unique(ignoreRecord: true),
                Toggle::make('is_admin')
                    ->label('Доступ в админку')
                    ->helperText('Даёт вход в эту панель. Выдавать только сотрудникам.'),
                // Optional on edit: only rehashes when a value is typed.
                TextInput::make('password')
                    ->label('Новый пароль')
                    ->password()
                    ->revealable()
                    ->minLength(8)
                    ->dehydrateStateUsing(fn (string $state): string => Hash::make($state))
                    ->dehydrated(fn (?string $state): bool => filled($state))
                    ->helperText('Оставьте пустым, чтобы не менять.'),
            ]);
    }
}
