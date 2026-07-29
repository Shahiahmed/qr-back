<?php

namespace App\Filament\Widgets;

use App\Models\Plan;
use Filament\Notifications\Notification;
use Filament\Widgets\Widget;
use Illuminate\Support\Facades\Artisan;

/**
 * Deploy/maintenance console for the platform operator: the handful of artisan
 * commands the project needs run by hand (migrate, storage:link, cache clear,
 * default plans) plus a live health readout. See CLAUDE.md §11 — these are
 * intentionally kept out of deploy.sh, so a one-tap button here beats SSH.
 *
 * Only a fixed whitelist runs; there is no free-form command input. Every action
 * re-checks the admin flag as defence in depth even though the panel is gated.
 */
class MaintenanceCommands extends Widget
{
    protected string $view = 'filament.widgets.maintenance-commands';

    protected int|string|array $columnSpan = 'full';

    /** Push it below the stats/chart — this is occasional, not daily, use. */
    protected static ?int $sort = 10;

    /** Output of the last command, shown inline so migrate/seed logs are visible. */
    public ?string $lastLabel = null;

    public ?string $lastOutput = null;

    /** Apply any pending migrations. `--force` because prod is non-interactive. */
    public function runMigrate(): void
    {
        $this->run('migrate', 'migrate', ['--force' => true], 'Миграции применены');
    }

    /** Symlink public/storage → storage/app/public (needed for venue images). */
    public function runStorageLink(): void
    {
        $this->run('storage:link', 'storage:link', [], 'Симлинк storage готов');
    }

    /** Drop cached config/routes/views/app cache after a deploy. */
    public function runClearCache(): void
    {
        $this->run('optimize:clear', 'optimize:clear', [], 'Кэш очищен');
    }

    /**
     * Seed the default plans. Runs whatever the catalogue state — but the button
     * carries a loud confirm when plans already exist, because the seeder is
     * updateOrCreate and resets the *default* tiers' prices back to placeholders
     * (CLAUDE.md §7/§11). `canSeedPlans()` only drives that warning copy now.
     */
    public function runSeedPlans(): void
    {
        $this->run(
            'db:seed',
            'db:seed --class=PlanSeeder',
            ['--class' => 'PlanSeeder', '--force' => true],
            'Стартовые тарифы засеяны',
        );
    }

    /**
     * Run one whitelisted command, capture its output, notify, and keep the log
     * on screen. Never receives user-supplied command names or arguments.
     */
    private function run(string $command, string $label, array $arguments, string $successTitle): void
    {
        abort_unless(auth()->user()?->is_admin === true, 403);

        try {
            $exitCode = Artisan::call($command, $arguments);
            $output = trim(Artisan::output());

            $this->lastLabel = $label;
            $this->lastOutput = $output === '' ? '(без вывода)' : $output;

            if ($exitCode === 0) {
                Notification::make()->title($successTitle)->success()->send();
            } else {
                Notification::make()
                    ->title('Команда завершилась с ошибкой')
                    ->body("Код выхода: {$exitCode}")
                    ->danger()
                    ->send();
            }
        } catch (\Throwable $e) {
            $this->lastLabel = $label;
            $this->lastOutput = $e->getMessage();

            Notification::make()->title('Ошибка выполнения')->body($e->getMessage())->danger()->send();
        }
    }

    /**
     * Live health readout the buttons act on. Each row is
     * [label, value, colour, ok?] — Blade renders them as badges.
     *
     * @return array<int, array{label: string, value: string, color: string}>
     */
    public function getChecks(): array
    {
        return [
            $this->migrationsCheck(),
            $this->storageLinkCheck(),
            $this->plansCheck(),
            $this->webpCheck(),
        ];
    }

    /** @return array{label: string, value: string, color: string} */
    private function migrationsCheck(): array
    {
        try {
            $migrator = app('migrator');
            $ran = $migrator->getRepository()->getRan();
            $files = array_keys($migrator->getMigrationFiles(database_path('migrations')));
            $pending = count(array_diff($files, $ran));

            return $pending === 0
                ? ['label' => 'Миграции', 'value' => 'Все применены', 'color' => 'success']
                : ['label' => 'Миграции', 'value' => "Ожидают: {$pending}", 'color' => 'warning'];
        } catch (\Throwable) {
            // Migrations table missing → a fresh DB that needs `migrate`.
            return ['label' => 'Миграции', 'value' => 'Не инициализированы', 'color' => 'danger'];
        }
    }

    /** @return array{label: string, value: string, color: string} */
    private function storageLinkCheck(): array
    {
        return file_exists(public_path('storage'))
            ? ['label' => 'Симлинк storage', 'value' => 'Есть', 'color' => 'success']
            : ['label' => 'Симлинк storage', 'value' => 'Нет — картинки отдают 404', 'color' => 'danger'];
    }

    /** @return array{label: string, value: string, color: string} */
    private function plansCheck(): array
    {
        $count = Plan::query()->count();

        return $count > 0
            ? ['label' => 'Тарифы', 'value' => "В каталоге: {$count}", 'color' => 'success']
            : ['label' => 'Тарифы', 'value' => 'Пусто — нужен засев', 'color' => 'warning'];
    }

    /** @return array{label: string, value: string, color: string} */
    private function webpCheck(): array
    {
        $ok = function_exists('gd_info') && ! empty(gd_info()['WebP Support']);

        return $ok
            ? ['label' => 'GD WebP', 'value' => 'Поддержка есть', 'color' => 'success']
            : ['label' => 'GD WebP', 'value' => 'Нет — загрузка фото упадёт', 'color' => 'danger'];
    }

    /** True on an empty catalogue — picks the seeder button's confirm copy. */
    public function canSeedPlans(): bool
    {
        return ! Plan::query()->exists();
    }
}
