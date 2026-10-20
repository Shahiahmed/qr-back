<?php

namespace App\Filament\Actions;

use App\Models\Establishment;
use Filament\Actions\Action;
use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Schemas\Components\Utilities\Set;
use Filament\Support\Icons\Heroicon;
use Illuminate\Support\Carbon;

/**
 * Manual trial extension from the panel. `trial_ends_at` is deliberately out of
 * `#[Fillable]` (owners must not extend themselves), so it is written with
 * forceFill — the panel is a trusted, is_admin-gated context, same as the
 * subscription override. Shared by the venues table, the edit page and the
 * "expiring soon" widget so all three behave identically.
 */
class ExtendTrialAction
{
    public static function make(string $name = 'extendTrial'): Action
    {
        return Action::make($name)
            ->label('Продлить пробный')
            ->icon(Heroicon::OutlinedClock)
            ->color('warning')
            // Grandfathered venues (null trial) are unlimited — stamping a trial
            // on them would *restrict* access, not extend it.
            ->visible(fn (Establishment $record): bool => $record->trial_ends_at !== null)
            ->modalHeading(fn (Establishment $record): string => "Пробный период: {$record->name}")
            ->modalDescription(fn (Establishment $record): ?string => $record->accessSource() === 'subscription'
                ? 'У меню активная подписка — она перекрывает пробный период. Продление вступит в силу, только когда подписка закончится.'
                : null)
            ->fillForm(fn (Establishment $record): array => [
                'days' => 7,
                'trial_ends_at' => self::extendFrom($record, 7),
            ])
            ->schema([
                TextInput::make('days')
                    ->label('Добавить дней')
                    ->helperText('Считается от текущего конца пробного, а если он уже прошёл — от сегодня.')
                    ->numeric()
                    ->minValue(1)
                    ->maxValue(365)
                    ->live(debounce: 300)
                    ->afterStateUpdated(function (?string $state, Set $set, Establishment $record): void {
                        $days = (int) $state;

                        if ($days > 0) {
                            $set('trial_ends_at', self::extendFrom($record, min($days, 365)));
                        }
                    }),
                DateTimePicker::make('trial_ends_at')
                    ->label('Пробный до')
                    ->helperText(fn (Establishment $record): string => 'Сейчас: '.$record->trial_ends_at?->format('d.m.Y H:i'))
                    ->seconds(false)
                    ->required(),
            ])
            ->action(function (array $data, Establishment $record): void {
                // Through the model, so `saved` drops the cached guest menu —
                // the access window is baked into that payload.
                $record->forceFill(['trial_ends_at' => Carbon::parse($data['trial_ends_at'])])->save();

                Notification::make()
                    ->title('Пробный период продлён до '.$record->trial_ends_at->format('d.m.Y H:i'))
                    ->success()
                    ->send();
            });
    }

    /** New end = later of (current trial end, now) + N days. */
    private static function extendFrom(Establishment $record, int $days): string
    {
        $base = $record->trial_ends_at && $record->trial_ends_at->isFuture()
            ? $record->trial_ends_at->copy()
            : Carbon::now();

        return $base->addDays($days)->format('Y-m-d H:i:s');
    }
}
