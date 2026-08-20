<?php

use App\Filament\Pages\PromoSettings;
use App\Models\PromoSetting;
use App\Models\User;
use App\Support\PublicPromo;
use Filament\Facades\Filament;
use Livewire\Livewire;

it('serves an active promo per-locale when enabled with content', function () {
    PromoSetting::current()->update([
        'enabled' => true,
        'badge_ru' => '−20%',
        'title_ru' => 'Скидка на подписку',
        'body_ru' => 'До конца месяца',
        'cta_label_ru' => 'Получить',
        'cta_url' => 'https://qr-menu.kz/ru/register',
        'title_kk' => '',           // empty → null
    ]);

    $data = $this->getJson('/api/promo')->assertOk()->json('data');

    expect($data['active'])->toBeTrue()
        ->and($data['ru']['badge'])->toBe('−20%')
        ->and($data['ru']['title'])->toBe('Скидка на подписку')
        ->and($data['ru']['cta_label'])->toBe('Получить')
        ->and($data['cta_url'])->toBe('https://qr-menu.kz/ru/register')
        ->and($data['kk']['title'])->toBeNull();
});

it('stays inactive and leaks no content when disabled', function () {
    PromoSetting::current()->update([
        'enabled' => false,
        'title_ru' => 'Секретная акция',
    ]);

    $data = $this->getJson('/api/promo')->assertOk()->json('data');

    expect($data['active'])->toBeFalse()
        ->and($data['ru']['title'])->toBeNull()
        ->and($data['cta_url'])->toBeNull();
});

it('honours the schedule window', function () {
    // Not started yet.
    PromoSetting::current()->update([
        'enabled' => true,
        'title_ru' => 'Скоро',
        'starts_at' => now()->addDay(),
        'ends_at' => null,
    ]);
    expect(PublicPromo::all()['active'])->toBeFalse();

    // Already ended.
    PromoSetting::current()->update([
        'starts_at' => now()->subDays(2),
        'ends_at' => now()->subDay(),
    ]);
    expect(PublicPromo::all()['active'])->toBeFalse();

    // Inside the window.
    PromoSetting::current()->update([
        'starts_at' => now()->subDay(),
        'ends_at' => now()->addDay(),
    ]);
    expect(PublicPromo::all()['active'])->toBeTrue();
});

it('caches the payload and drops it when settings change', function () {
    PromoSetting::current()->update(['enabled' => true, 'title_ru' => 'Первый']);

    // Warm the cache.
    expect(PublicPromo::all()['ru']['title'])->toBe('Первый');

    PromoSetting::current()->update(['title_ru' => 'Второй']);

    // The model event cleared the cache.
    expect(PublicPromo::all()['ru']['title'])->toBe('Второй');
});

it('lets an admin edit the promo from the panel', function () {
    $admin = User::factory()->create(['is_admin' => true]);
    Filament::setCurrentPanel('admin');
    $this->actingAs($admin);

    Livewire::test(PromoSettings::class)
        ->fillForm([
            'enabled' => true,
            'title_ru' => 'Из панели',
            'title_kk' => 'Панельден',
        ])
        ->call('save')
        ->assertHasNoFormErrors();

    expect(PromoSetting::current()->enabled)->toBeTrue()
        ->and(PromoSetting::current()->title_ru)->toBe('Из панели')
        ->and(PromoSetting::current()->title_kk)->toBe('Панельден');
});

it('is a singleton — current() always returns one row', function () {
    PromoSetting::current();
    PromoSetting::current();

    expect(PromoSetting::query()->count())->toBe(1);
});
