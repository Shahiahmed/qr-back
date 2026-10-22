<?php

namespace App\Filament\Resources\Users\Tables;

use App\Models\User;
use App\Support\Phone;
use Filament\Actions\Action;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class UsersTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn (Builder $query) => $query->withCount('establishments'))
            ->columns([
                TextColumn::make('name')
                    ->label('Имя')
                    ->weight('bold')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('email')
                    ->label('Email')
                    ->searchable(),
                TextColumn::make('phone')
                    ->label('WhatsApp')
                    ->formatStateUsing(fn (?string $state): ?string => Phone::format($state))
                    ->url(fn (User $record): ?string => Phone::whatsappUrl($record->phone), shouldOpenInNewTab: true)
                    ->color('success')
                    ->placeholder('—')
                    ->searchable(),
                TextColumn::make('establishments_count')
                    ->label('Заведения')
                    ->alignRight()
                    ->badge()
                    ->color('info'),
                IconColumn::make('is_admin')
                    ->label('Админ')
                    ->boolean(),
                TextColumn::make('created_at')
                    ->label('Регистрация')
                    ->date('d.m.Y')
                    ->sortable(),
            ])
            ->defaultSort('created_at', 'desc')
            ->recordActions([
                Action::make('whatsapp')
                    ->label('WhatsApp')
                    ->icon(Heroicon::OutlinedChatBubbleLeftRight)
                    ->color('success')
                    ->visible(fn (User $record): bool => filled($record->phone))
                    ->url(fn (User $record): ?string => Phone::whatsappUrl($record->phone))
                    ->openUrlInNewTab(),
                EditAction::make(),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
            ]);
    }
}
