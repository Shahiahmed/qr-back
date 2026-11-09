<?php

namespace App\Filament\Resources\Establishments\Tables;

use App\Filament\Actions\BlockMenuAction;
use App\Filament\Actions\ExtendTrialAction;
use App\Models\Establishment;
use App\Support\Phone;
use Filament\Actions\Action;
use Filament\Actions\ActionGroup;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

/*
 * Kept narrow on purpose: a laptop-width panel used to need a horizontal scroll
 * to reach the actions. Secondary facts ride as descriptions under the primary
 * ones (slug under the name, email under the owner), the two menu counters
 * share one cell, and the row actions collapse into a single "⋯" menu.
 */
class EstablishmentsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            // Counts + each venue's own grant in one query — no N+1 across the list.
            ->modifyQueryUsing(fn (Builder $query) => $query
                ->withStarterCounts()
                ->with(['user', 'currentSubscription']))
            ->columns([
                TextColumn::make('name')
                    ->label('Заведение')
                    ->weight('bold')
                    ->description(fn (Establishment $record): string => "/m/{$record->slug}")
                    ->searchable(['name', 'slug'])
                    ->sortable(),
                TextColumn::make('user.name')
                    ->label('Владелец')
                    ->description(fn (Establishment $record): ?string => collect([
                        $record->user?->email,
                        Phone::format($record->user?->phone),
                    ])->filter()->implode(' · '))
                    ->searchable(['name', 'email', 'phone']),
                TextColumn::make('menu')
                    ->label('Меню')
                    ->state(fn (Establishment $record): string => "{$record->categories_count} разд. · {$record->dishes_count} блюд")
                    // The auto-applied starter, never edited: the owner signed up
                    // and stopped — worth a message.
                    ->color(fn (Establishment $record): string => $record->isStarterUntouched() ? 'warning' : 'gray')
                    ->description(fn (Establishment $record): ?string => $record->isStarterUntouched() ? 'шаблон не тронут' : null)
                    // Below 1280px the sidebar leaves too little room; the counts
                    // are on the venue page's tabs anyway.
                    ->visibleFrom('xl'),
                TextColumn::make('access')
                    ->label('Доступ')
                    ->badge()
                    ->state(function (Establishment $record): string {
                        if ($record->isBlocked()) {
                            return 'Заблокировано';
                        }

                        if ($record->isExpired()) {
                            return 'Истёк';
                        }

                        $days = $record->daysLeft();

                        if ($days === null) {
                            return 'Без ограничений';
                        }

                        $suffix = $record->accessSource() === 'trial' ? ' (проб.)' : '';

                        return $days.' дн.'.$suffix;
                    })
                    ->color(fn (Establishment $record): string => match (true) {
                        $record->isBlocked(), $record->isExpired() => 'danger',
                        $record->accessSource() === 'subscription' => 'success',
                        $record->accessSource() === 'trial' => 'warning',
                        default => 'gray',
                    })
                    ->description(fn (Establishment $record): ?string => $record->isBlocked()
                        ? 'с '.$record->blocked_at->format('d.m.Y')
                        : $record->accessEndsAt()?->format('d.m.Y'))
                    ->tooltip(fn (Establishment $record): ?string => $record->blocked_reason),
                // Bound chat = waiter calls and orders reach the staff. An empty
                // one is a nudge-worthy owner (the "⋯ → WhatsApp" action is right there).
                IconColumn::make('telegram')
                    ->label('Telegram')
                    ->state(fn (Establishment $record): bool => filled($record->telegram_chat_id))
                    ->boolean()
                    ->trueIcon(Heroicon::OutlinedCheckCircle)
                    ->falseIcon(Heroicon::OutlinedMinusCircle)
                    ->falseColor('gray')
                    ->tooltip(fn (Establishment $record): string => filled($record->telegram_chat_id)
                        ? 'Telegram подключён: вызовы и заказы приходят персоналу'
                        : 'Telegram не подключён')
                    ->alignCenter(),
                TextColumn::make('theme')
                    ->label('Тема')
                    ->badge()
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('created_at')
                    ->label('Создано')
                    ->dateTime('d.m.Y H:i')
                    ->sortable()
                    ->visibleFrom('xl')
                    ->toggleable(),
            ])
            ->defaultSort('created_at', 'desc')
            ->filters([
                TernaryFilter::make('blocked')
                    ->label('Блокировка')
                    ->placeholder('Все')
                    ->trueLabel('Заблокированные')
                    ->falseLabel('Активные')
                    ->nullable()
                    ->attribute('blocked_at'),
                TernaryFilter::make('telegram')
                    ->label('Telegram')
                    ->placeholder('Все')
                    ->trueLabel('Подключён')
                    ->falseLabel('Не подключён')
                    ->nullable()
                    ->attribute('telegram_chat_id'),
                TernaryFilter::make('starter')
                    ->label('Меню')
                    ->placeholder('Все')
                    ->trueLabel('Шаблон не тронут')
                    ->falseLabel('Своё меню')
                    ->queries(
                        true: fn (Builder $query) => $query->whereStarterUntouched(),
                        false: fn (Builder $query) => $query->whereNot(fn (Builder $q) => $q->whereStarterUntouched()),
                    ),
            ])
            ->recordActions([
                ActionGroup::make([
                    Action::make('menu')
                        ->label('Меню гостя')
                        ->icon(Heroicon::OutlinedQrCode)
                        ->url(
                            fn (Establishment $record): string => rtrim((string) config('app.frontend_url'), '/')."/m/{$record->slug}",
                        )
                        ->openUrlInNewTab(),
                    Action::make('whatsapp')
                        ->label('Написать в WhatsApp')
                        ->icon(Heroicon::OutlinedChatBubbleLeftRight)
                        ->color('success')
                        ->visible(fn (Establishment $record): bool => filled($record->user?->phone))
                        ->url(fn (Establishment $record): ?string => Phone::whatsappUrl($record->user?->phone))
                        ->openUrlInNewTab(),
                    ExtendTrialAction::make(),
                    BlockMenuAction::block(),
                    BlockMenuAction::unblock(),
                    EditAction::make(),
                ]),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
            ]);
    }
}
