<?php

namespace App\Filament\Resources\Establishments\Tables;

use App\Models\Establishment;
use Filament\Actions\Action;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class EstablishmentsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            // Counts in one query — no N+1 across the list.
            ->modifyQueryUsing(fn (Builder $query) => $query->withCount(['categories', 'dishes']))
            ->columns([
                TextColumn::make('name')
                    ->label('Заведение')
                    ->weight('bold')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('user.name')
                    ->label('Владелец')
                    ->description(fn (Establishment $record) => $record->user?->email)
                    ->searchable(),
                TextColumn::make('slug')
                    ->label('Адрес меню')
                    ->badge()
                    ->color('gray')
                    ->searchable(),
                TextColumn::make('theme')
                    ->label('Тема')
                    ->badge(),
                TextColumn::make('categories_count')
                    ->label('Разделы')
                    ->alignRight()
                    ->badge()
                    ->color('info'),
                TextColumn::make('dishes_count')
                    ->label('Блюда')
                    ->alignRight()
                    ->badge()
                    ->color('success'),
                TextColumn::make('created_at')
                    ->label('Создано')
                    ->date('d.m.Y')
                    ->sortable(),
            ])
            ->defaultSort('created_at', 'desc')
            ->recordActions([
                Action::make('menu')
                    ->label('Меню гостя')
                    ->icon(Heroicon::OutlinedQrCode)
                    ->color('gray')
                    ->url(
                        fn (Establishment $record): string => rtrim((string) config('app.frontend_url'), '/')."/m/{$record->slug}",
                    )
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
