<?php

namespace App\Filament\Resources\Plans\Tables;

use App\Models\Plan;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class PlansTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->defaultSort('sort')
            ->columns([
                TextColumn::make('name_ru')
                    ->label('Название')
                    ->description(fn (Plan $r): ?string => $r->tagline_ru)
                    ->searchable(),
                // Stored in tiyn; show tenge. Strike-through original when discounted.
                TextColumn::make('price')
                    ->label('Цена')
                    ->formatStateUsing(fn (int $state): string => number_format($state / 100, 0, '.', ' ').' ₸')
                    ->description(fn (Plan $r): ?string => $r->discount_percent > 0
                        ? '−'.$r->discount_percent.'% → '.number_format($r->discountedPrice() / 100, 0, '.', ' ').' ₸'
                        : null)
                    ->sortable(),
                TextColumn::make('period')
                    ->label('Срок')
                    ->formatStateUsing(fn (string $state): string => match ($state) {
                        'year' => 'год',
                        'halfyear' => '6 мес',
                        default => 'месяц',
                    })
                    ->badge(),
                TextColumn::make('max_establishments')
                    ->label('Заведений')
                    ->placeholder('∞')
                    ->sortable(),
                IconColumn::make('is_active')
                    ->label('Активен')
                    ->boolean(),
                IconColumn::make('is_featured')
                    ->label('Популярный')
                    ->boolean(),
                TextColumn::make('sort')
                    ->label('Порядок')
                    ->numeric()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->recordActions([
                EditAction::make(),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
            ]);
    }
}
