<?php

namespace App\Filament\Widgets;

use App\Models\Establishment;
use App\Models\SubscriptionRequest;
use App\Models\User;
use Filament\Widgets\StatsOverviewWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

/**
 * Platform-level counters only: pending subscription requests, owners, venues,
 * menus about to lose access.
 * Per-venue menu depth (categories / dishes) belongs in the owner cabinet, not here.
 */
class PlatformStats extends StatsOverviewWidget
{
    protected static ?int $sort = 1;

    protected ?string $pollingInterval = null;

    protected function getStats(): array
    {
        $owners = User::query()->where('is_admin', false)->count();
        $newOwners = User::query()
            ->where('is_admin', false)
            ->where('created_at', '>=', now()->subDays(7))
            ->count();

        $pendingRequests = SubscriptionRequest::query()
            ->whereIn('status', SubscriptionRequest::PENDING)
            ->count();

        $expiringSoon = Establishment::query()
            ->whereAccessEndsBetween(now(), now()->addDays(7))
            ->count();

        return [
            Stat::make('Заявки на подписку', $pendingRequests)
                ->description($pendingRequests > 0 ? 'Ждут решения' : 'Новых нет')
                ->color($pendingRequests > 0 ? 'warning' : 'gray'),

            Stat::make('Владельцы', $owners)
                ->description($newOwners > 0 ? "+{$newOwners} за 7 дней" : 'Новых за неделю нет')
                ->color($newOwners > 0 ? 'success' : 'gray'),

            Stat::make('Заведения', Establishment::query()->count()),

            Stat::make('Истекают за 7 дней', $expiringSoon)
                ->description($expiringSoon > 0 ? 'Пора позвонить владельцам' : 'Никто не истекает')
                ->color($expiringSoon > 0 ? 'danger' : 'gray'),
        ];
    }
}
