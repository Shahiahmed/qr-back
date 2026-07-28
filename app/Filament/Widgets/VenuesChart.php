<?php

namespace App\Filament\Widgets;

use App\Models\Establishment;
use Filament\Widgets\ChartWidget;

/**
 * New venues per day over the last two weeks.
 *
 * Grouping is done in PHP, not with SQL date functions, so the same code runs
 * on MySQL (prod) and the sqlite in-memory test database without dialect gaps.
 */
class VenuesChart extends ChartWidget
{
    protected ?string $heading = 'Новые заведения за 14 дней';

    protected int|string|array $columnSpan = 'full';

    protected function getData(): array
    {
        $since = now()->subDays(13)->startOfDay();

        $counts = Establishment::query()
            ->where('created_at', '>=', $since)
            ->get(['created_at'])
            ->groupBy(fn (Establishment $e) => $e->created_at->format('Y-m-d'))
            ->map->count();

        $days = collect(range(13, 0))->map(fn (int $back) => now()->subDays($back)->startOfDay());

        return [
            'datasets' => [
                [
                    'label' => 'Заведения',
                    'data' => $days->map(fn ($day) => $counts->get($day->format('Y-m-d'), 0))->all(),
                    'borderColor' => '#f59e0b',
                    'backgroundColor' => 'rgba(245, 158, 11, 0.15)',
                    'fill' => true,
                    'tension' => 0.3,
                ],
            ],
            'labels' => $days->map(fn ($day) => $day->format('d.m'))->all(),
        ];
    }

    protected function getType(): string
    {
        return 'line';
    }
}
