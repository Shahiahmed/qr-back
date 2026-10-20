<?php

namespace App\Filament\Widgets;

use App\Filament\Actions\ExtendTrialAction;
use App\Filament\Resources\Establishments\EstablishmentResource;
use App\Models\Establishment;
use Filament\Actions\Action;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Filament\Widgets\TableWidget;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Carbon;

/**
 * Sales call list: menus whose access ends soon (or just ended), so someone
 * rings the owner before the guest page goes 404. Ordered by end date —
 * the most urgent first.
 */
class ExpiringVenues extends TableWidget
{
    protected static ?int $sort = 2;

    protected int|string|array $columnSpan = 'full';

    protected static ?string $heading = 'Скоро истекают';

    public function table(Table $table): Table
    {
        return $table
            ->query(fn (): Builder => Establishment::query()
                ->with(['user', 'currentSubscription'])
                ->orderByAccessEnd())
            ->columns([
                TextColumn::make('name')
                    ->label('Заведение')
                    ->weight('bold')
                    ->description(fn (Establishment $record): string => "/m/{$record->slug}"),
                TextColumn::make('user.name')
                    ->label('Владелец')
                    ->description(fn (Establishment $record): ?string => $record->user?->email),
                TextColumn::make('phone')
                    ->label('Телефон')
                    ->placeholder('—')
                    ->copyable(),
                TextColumn::make('access')
                    ->label('Доступ')
                    ->badge()
                    ->state(function (Establishment $record): string {
                        $source = $record->accessSource() === 'subscription' ? 'подписка' : 'пробный';

                        if ($record->isExpired()) {
                            return 'Истёк';
                        }

                        return $record->daysLeft().' дн. · '.$source;
                    })
                    ->color(fn (Establishment $record): string => match (true) {
                        $record->isExpired() => 'danger',
                        $record->daysLeft() <= 2 => 'warning',
                        default => 'gray',
                    }),
                TextColumn::make('ends')
                    ->label('Окончание')
                    // Shows the lapsed grant's date too (accessEndsAt() would fall
                    // back to the older trial date once the grant has passed).
                    ->state(fn (Establishment $record): ?string => ($record->currentSubscription?->ends_at ?? $record->trial_ends_at)?->format('d.m.Y H:i')),
            ])
            ->filters([
                SelectFilter::make('window')
                    ->label('Период')
                    ->options([
                        'soon' => 'Истекают в ближайшие 7 дней',
                        'expired' => 'Истекли за последние 14 дней',
                        'both' => 'И то, и другое',
                    ])
                    ->default('soon')
                    ->selectablePlaceholder(false)
                    ->query(function (Builder $query, array $data): Builder {
                        $now = Carbon::now();

                        return match ($data['value'] ?? 'soon') {
                            'expired' => $query->whereAccessEndsBetween($now->copy()->subDays(14), $now),
                            'both' => $query->whereAccessEndsBetween($now->copy()->subDays(14), $now->copy()->addDays(7)),
                            default => $query->whereAccessEndsBetween($now, $now->copy()->addDays(7)),
                        };
                    }),
            ])
            ->recordActions([
                ExtendTrialAction::make(),
                Action::make('open')
                    ->label('Открыть')
                    ->icon(Heroicon::OutlinedArrowTopRightOnSquare)
                    ->color('gray')
                    ->url(fn (Establishment $record): string => EstablishmentResource::getUrl('edit', ['record' => $record])),
            ])
            ->emptyStateHeading('Ничего не истекает')
            ->emptyStateDescription('В выбранном периоде у меню не заканчивается доступ.')
            ->defaultPaginationPageOption(10);
    }
}
