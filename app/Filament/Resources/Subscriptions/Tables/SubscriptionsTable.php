<?php

namespace App\Filament\Resources\Subscriptions\Tables;

use App\Models\Subscription;
use Filament\Actions\Action;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\Select;
use Filament\Notifications\Notification;
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
            ])
            ->recordActions([
                // Subscriptions are normally created by the approval flow only;
                // this manual edit is a staff override (fix a wrong date, close a
                // grant issued by mistake). Status/ends_at aren't fillable, so we
                // write them with forceFill — same trusted-context pattern as the
                // user is_admin toggle. Saving drops the menu's guest cache via
                // the model's InvalidatesPublicMenu trait.
                Action::make('edit')
                    ->label('Изменить')
                    ->icon('heroicon-o-pencil-square')
                    ->fillForm(fn (Subscription $record): array => [
                        'status' => $record->status,
                        'ends_at' => $record->ends_at,
                    ])
                    ->schema([
                        Select::make('status')
                            ->label('Статус')
                            ->options(self::STATUS_LABELS)
                            ->required(),
                        DateTimePicker::make('ends_at')
                            ->label('Окончание доступа')
                            ->seconds(false)
                            ->helperText('Пусто = бессрочно.'),
                    ])
                    ->action(function (Subscription $record, array $data): void {
                        $record->forceFill([
                            'status' => $data['status'],
                            'ends_at' => $data['ends_at'] ?: null,
                        ])->save();

                        Notification::make()->title('Подписка обновлена')->success()->send();
                    }),
                DeleteAction::make()
                    ->modalDescription('Удалить подписку? Доступ меню вернётся к пробному сроку (или станет бессрочным, если пробного не было).'),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
            ]);
    }
}
