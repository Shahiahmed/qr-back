<?php

namespace App\Filament\Resources\Subscriptions\Tables;

use App\Models\Subscription;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;

class SubscriptionsTable
{
    /** Status → human label. */
    private const STATUS_LABELS = [
        Subscription::STATUS_ACTIVE => 'Активна',
        Subscription::STATUS_EXPIRED => 'Истекла',
        Subscription::STATUS_CANCELLED => 'Закрыта',
    ];

    public static function configure(Table $table): Table
    {
        return $table
            ->defaultSort('created_at', 'desc')
            ->columns([
                TextColumn::make('user.name')
                    ->label('Владелец')
                    ->description(fn (Subscription $r): ?string => $r->user?->email)
                    ->searchable(),
                TextColumn::make('plan.name_ru')
                    ->label('Тариф')
                    ->placeholder('—'),
                TextColumn::make('status')
                    ->label('Статус')
                    ->badge()
                    ->formatStateUsing(fn (string $state): string => self::STATUS_LABELS[$state] ?? $state)
                    ->color(fn (string $state): string => match ($state) {
                        Subscription::STATUS_ACTIVE => 'success',
                        Subscription::STATUS_EXPIRED => 'danger',
                        default => 'gray',
                    }),
                TextColumn::make('starts_at')
                    ->label('Начало')
                    ->date('d.m.Y')
                    ->placeholder('—'),
                TextColumn::make('ends_at')
                    ->label('Окончание')
                    ->date('d.m.Y')
                    ->placeholder('бессрочно'),
                TextColumn::make('left')
                    ->label('Осталось')
                    ->badge()
                    ->state(function (Subscription $record): string {
                        if (! $record->isActive()) {
                            return '—';
                        }

                        $days = $record->daysLeft();

                        return $days === null ? 'бессрочно' : $days.' дн.';
                    })
                    ->color(fn (Subscription $record): string => $record->isActive() ? 'success' : 'gray'),
            ])
            ->filters([
                SelectFilter::make('status')
                    ->label('Статус')
                    ->options(self::STATUS_LABELS)
                    ->default(Subscription::STATUS_ACTIVE),
            ]);
    }
}
