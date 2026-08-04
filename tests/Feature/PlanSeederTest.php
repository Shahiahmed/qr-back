<?php

use App\Models\Plan;
use Database\Seeders\PlanSeeder;

it('seeds the free tier with the 3/5 content caps', function () {
    (new PlanSeeder())->run();

    $free = Plan::where('name_ru', 'Бесплатный')->sole();

    expect($free->price)->toBe(0)
        ->and($free->max_categories)->toBe(3)
        ->and($free->max_dishes_per_category)->toBe(5);
});

it('leaves the paid tiers without content caps', function () {
    (new PlanSeeder())->run();

    foreach (['На 6 месяцев', 'На год', 'Премиум'] as $name) {
        $plan = Plan::where('name_ru', $name)->sole();
        expect($plan->max_categories)->toBeNull()
            ->and($plan->max_dishes_per_category)->toBeNull();
    }
});

it('seeds a Премиум plan priced 200 000 ₸ per year', function () {
    (new PlanSeeder())->run();

    $premium = Plan::where('name_ru', 'Премиум')->sole();

    expect($premium->price)->toBe(200_000 * 100)
        ->and($premium->period)->toBe('year')
        ->and($premium->is_featured)->toBeFalse()
        ->and($premium->is_active)->toBeTrue();
});

it('is idempotent — a re-run does not duplicate rows', function () {
    (new PlanSeeder())->run();
    (new PlanSeeder())->run();

    expect(Plan::count())->toBe(4);
});
