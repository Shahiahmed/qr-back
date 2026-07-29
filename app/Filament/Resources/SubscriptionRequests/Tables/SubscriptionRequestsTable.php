<?php

namespace App\Filament\Resources\SubscriptionRequests\Tables;

use App\Models\SubscriptionRequest;
use App\Support\SubscriptionService;
use Filament\Actions\Action;
use Filament\Notifications\Notification;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;

class SubscriptionRequestsTable
{
    /** Status → human label. */
    private const STATUS_LABELS = [
        SubscriptionRequest::STATUS_NEW => 'Новая',
        SubscriptionRequest::STATUS_APPROVED => 'Одобрена',
        SubscriptionRequest::STATUS_REJECTED => 'Отклонена',
    ];

    public static function configure(Table $table): Table
    {
        return $table
            ->defaultSort('created_at', 'desc')
            ->columns([
                TextColumn::make('user.name')
                    ->label('Владелец')
                    ->description(fn (SubscriptionRequest $r): ?string => $r->user?->email)
                    ->searchable(),
                TextColumn::make('establishment.name')
                    ->label('Меню')
                    ->placeholder('—'),
                TextColumn::make('plan.name_ru')
                    ->label('Тариф')
                    ->placeholder('—'),
                TextColumn::make('status')
                    ->label('Статус')
                    ->badge()
                    ->formatStateUsing(fn (string $state): string => self::STATUS_LABELS[$state] ?? $state)
                    ->color(fn (string $state): string => match ($state) {
                        SubscriptionRequest::STATUS_APPROVED => 'success',
                        SubscriptionRequest::STATUS_REJECTED => 'danger',
                        default => 'warning',
                    }),
                TextColumn::make('contact_phone')
                    ->label('Телефон')
                    ->placeholder('—'),
                TextColumn::make('note')
                    ->label('Комментарий')
                    ->placeholder('—')
                    ->wrap()
                    ->toggleable(),
                TextColumn::make('created_at')
                    ->label('Подана')
                    ->dateTime('d.m.Y H:i')
                    ->sortable(),
                TextColumn::make('reviewed_at')
                    ->label('Рассмотрена')
                    ->dateTime('d.m.Y H:i')
                    ->placeholder('—')
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                SelectFilter::make('status')
                    ->label('Статус')
                    ->options(self::STATUS_LABELS)
                    ->default(SubscriptionRequest::STATUS_NEW),
            ])
            ->recordActions([
                Action::make('approve')
                    ->label('Одобрить')
                    ->icon('heroicon-o-check-circle')
                    ->color('success')
                    ->visible(fn (SubscriptionRequest $r): bool => $r->isPending())
                    ->requiresConfirmation()
                    ->modalHeading('Одобрить заявку?')
                    ->modalDescription('Меню будет выдана подписка, предыдущая активная для этого меню закроется.')
                    ->action(function (SubscriptionRequest $record): void {
                        SubscriptionService::approve($record, auth()->user());

                        Notification::make()->title('Заявка одобрена, подписка выдана')->success()->send();
                    }),
                Action::make('reject')
                    ->label('Отклонить')
                    ->icon('heroicon-o-x-circle')
                    ->color('danger')
                    ->visible(fn (SubscriptionRequest $r): bool => $r->isPending())
                    ->requiresConfirmation()
                    ->modalHeading('Отклонить заявку?')
                    ->action(function (SubscriptionRequest $record): void {
                        SubscriptionService::reject($record, auth()->user());

                        Notification::make()->title('Заявка отклонена')->send();
                    }),
            ]);
    }
}
