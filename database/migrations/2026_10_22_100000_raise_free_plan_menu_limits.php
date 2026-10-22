<?php

use App\Support\PublicPlans;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Free tier (and the trial, which falls back to it) goes from 3 sections ×
 * 5 dishes to 10 × 10. PlanSeeder is never run on deploy, so the live row is
 * updated here — but only while it still holds the old seeded defaults, so a
 * limit the admin already changed in /admin is left alone.
 */
return new class extends Migration
{
    private const OLD_FEATURE = ['ru' => 'До 3 разделов, 5 блюд в разделе', 'kk' => '3 бөлімге дейін, бөлімде 5 тағам'];

    private const NEW_FEATURE = ['ru' => 'До 10 разделов, 10 блюд в разделе', 'kk' => '10 бөлімге дейін, бөлімде 10 тағам'];

    public function up(): void
    {
        $this->swap(3, 5, 10, 10, self::OLD_FEATURE, self::NEW_FEATURE);
    }

    public function down(): void
    {
        $this->swap(10, 10, 3, 5, self::NEW_FEATURE, self::OLD_FEATURE);
    }

    private function swap(int $fromCats, int $fromDishes, int $toCats, int $toDishes, array $fromFeature, array $toFeature): void
    {
        $plans = DB::table('plans')
            ->where('price', 0)
            ->where('max_categories', $fromCats)
            ->where('max_dishes_per_category', $fromDishes)
            ->get(['id', 'features']);

        foreach ($plans as $plan) {
            $features = json_decode($plan->features ?? '[]', true) ?: [];
            $features = array_map(fn ($f) => $f == $fromFeature ? $toFeature : $f, $features);

            DB::table('plans')->where('id', $plan->id)->update([
                'max_categories' => $toCats,
                'max_dishes_per_category' => $toDishes,
                'features' => json_encode($features, JSON_UNESCAPED_UNICODE),
            ]);
        }

        // Raw updates skip the model events that normally clear the landing cache.
        PublicPlans::forget();
    }
};
