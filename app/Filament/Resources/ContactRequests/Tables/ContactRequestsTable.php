<?php

namespace App\Filament\Resources\ContactRequests\Tables;

use App\Models\ContactRequest;
use App\Support\Phone;
use Filament\Actions\Action;
use Filament\Actions\DeleteAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;

class ContactRequestsTable
{
    /** Status → human label. */
    private const STATUS_LABELS = [
        ContactRequest::STATUS_NEW => 'Новая',
        ContactRequest::STATUS_DONE => 'Обработана',
    ];

    public static function configure(Table $table): Table
    {
        return $table
            ->defaultSort('created_at', 'desc')
            ->columns([
                TextColumn::make('name')
                    ->label('Имя')
                    ->searchable(),
                TextColumn::make('phone')
                    ->label('Телефон')
                    ->formatStateUsing(fn (string $state): ?string => Phone::format($state))
                    ->url(fn (ContactRequest $r): ?string => Phone::whatsappUrl($r->phone), shouldOpenInNewTab: true)
                    ->color('primary')
                    ->searchable(),
                TextColumn::make('note')
                    ->label('Комментарий')
                    ->placeholder('—')
                    ->wrap(),
                TextColumn::make('status')
                    ->label('Статус')
                    ->badge()
                    ->formatStateUsing(fn (string $state): string => self::STATUS_LABELS[$state] ?? $state)
                    ->color(fn (string $state): string => $state === ContactRequest::STATUS_DONE ? 'success' : 'warning'),
                TextColumn::make('created_at')
                    ->label('Получена')
                    ->dateTime('d.m.Y H:i')
                    ->sortable(),
            ])
            ->filters([
                SelectFilter::make('status')
                    ->label('Статус')
                    ->options(self::STATUS_LABELS),
            ])
            ->recordActions([
                Action::make('done')
                    ->label('Обработана')
                    ->icon('heroicon-o-check-circle')
                    ->color('success')
                    ->visible(fn (ContactRequest $r): bool => $r->isNew())
                    ->action(fn (ContactRequest $record) => $record->forceFill(['status' => ContactRequest::STATUS_DONE])->save()),
                DeleteAction::make(),
            ]);
    }
}
