<?php

namespace App\Filament\Resources\Establishments\Tables;

use App\Filament\Actions\ExtendTrialAction;
use App\Models\Establishment;
use Filament\Actions\Action;
use Filament\Actions\ActionGroup;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
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
                ->withCount(['categories', 'dishes'])
                ->with('currentSubscription'))
            ->columns([
                TextColumn::make('name')
                    ->label('Заведение')
                    ->weight('bold')
                    ->description(fn (Establishment $record): string => "/m/{$record->slug}")
                    ->searchable(['name', 'slug'])
                    ->sortable(),
                TextColumn::make('user.name')
                    ->label('Владелец')
                    ->description(fn (Establishment $record) => $record->user?->email)
                    ->searchable(),
                TextColumn::make('menu')
                    ->label('Меню')
                    ->state(fn (Establishment $record): string => "{$record->categories_count} разд. · {$record->dishes_count} блюд")
                    ->color('gray')
                    // Below 1280px the sidebar leaves too little room; the counts
                    // are on the venue page's tabs anyway.
                    ->visibleFrom('xl'),
                TextColumn::make('access')
                    ->label('Доступ')
                    ->badge()
                    ->state(function (Establishment $record): string {
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
                        $record->isExpired() => 'danger',
                        $record->accessSource() === 'subscription' => 'success',
                        $record->accessSource() === 'trial' => 'warning',
                        default => 'gray',
                    })
                    ->description(fn (Establishment $record): ?string => $record->accessEndsAt()?->format('d.m.Y')),
                TextColumn::make('theme')
                    ->label('Тема')
                    ->badge()
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('created_at')
                    ->label('Создано')
                    ->date('d.m.Y')
                    ->sortable()
                    ->visibleFrom('xl')
                    ->toggleable(),
            ])
            ->defaultSort('created_at', 'desc')
            ->recordActions([
                ActionGroup::make([
                    Action::make('menu')
                        ->label('Меню гостя')
                        ->icon(Heroicon::OutlinedQrCode)
                        ->url(
                            fn (Establishment $record): string => rtrim((string) config('app.frontend_url'), '/')."/m/{$record->slug}",
                        )
                        ->openUrlInNewTab(),
                    ExtendTrialAction::make(),
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
