<?php

namespace App\Filament\Actions;

use App\Models\Establishment;
use Filament\Actions\Action;
use Filament\Forms\Components\Textarea;
use Filament\Notifications\Notification;
use Filament\Support\Icons\Heroicon;
use Illuminate\Support\Carbon;

/**
 * Platform block of a guest menu. `blocked_at` / `blocked_reason` are out of
 * `#[Fillable]` (an owner must never unblock themselves), so they are written
 * with forceFill — the panel is the trusted, is_admin-gated context. Saving
 * through the model fires `saved`, which drops the cached guest menu, so the
 * block (and the unblock) takes effect on the very next scan.
 */
class BlockMenuAction
{
    public static function block(string $name = 'blockMenu'): Action
    {
        return Action::make($name)
            ->label('Заблокировать меню')
            ->icon(Heroicon::OutlinedNoSymbol)
            ->color('danger')
            ->visible(fn (Establishment $record): bool => ! $record->isBlocked())
            ->requiresConfirmation()
            ->modalHeading(fn (Establishment $record): string => "Заблокировать «{$record->name}»?")
            ->modalDescription('Гости по QR-коду увидят «Меню временно недоступно». Данные меню не удаляются — разблокировать можно в любой момент.')
            ->modalSubmitActionLabel('Заблокировать')
            ->schema([
                Textarea::make('blocked_reason')
                    ->label('Причина (видна только админам)')
                    ->rows(2)
                    ->maxLength(500),
            ])
            ->action(function (array $data, Establishment $record): void {
                $record->forceFill([
                    'blocked_at' => Carbon::now(),
                    'blocked_reason' => filled($data['blocked_reason'] ?? null) ? $data['blocked_reason'] : null,
                ])->save();

                Notification::make()->title('Меню заблокировано')->success()->send();
            });
    }

    public static function unblock(string $name = 'unblockMenu'): Action
    {
        return Action::make($name)
            ->label('Разблокировать меню')
            ->icon(Heroicon::OutlinedLockOpen)
            ->color('success')
            ->visible(fn (Establishment $record): bool => $record->isBlocked())
            ->requiresConfirmation()
            ->modalHeading(fn (Establishment $record): string => "Разблокировать «{$record->name}»?")
            ->modalDescription(fn (Establishment $record): string => $record->isExpired()
                ? 'Внимание: срок доступа этого меню уже истёк — после разблокировки гости всё равно увидят 404, пока не продлите пробный или подписку.'
                : 'Меню снова откроется гостям по QR-коду.')
            ->modalSubmitActionLabel('Разблокировать')
            ->action(function (Establishment $record): void {
                $record->forceFill(['blocked_at' => null, 'blocked_reason' => null])->save();

                Notification::make()->title('Меню снова доступно')->success()->send();
            });
    }
}
