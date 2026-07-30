<?php

use App\Filament\Pages\SeoSettings;
use App\Models\SeoSetting;
use App\Models\User;
use App\Support\PublicSeo;
use Filament\Facades\Filament;
use Livewire\Livewire;

it('serves per-locale meta, falling back to null on empty fields', function () {
    SeoSetting::current()->update([
        'title_ru' => 'QR-меню для ресторанов',
        'description_ru' => 'Меню по QR без приложения',
        'title_kk' => '',              // empty → null (front end falls back)
        'canonical_host' => 'qmenu.kz',
    ]);

    $data = $this->getJson('/api/seo')->assertOk()->json('data');

    expect($data['ru']['title'])->toBe('QR-меню для ресторанов')
        ->and($data['ru']['description'])->toBe('Меню по QR без приложения')
        ->and($data['kk']['title'])->toBeNull()
        ->and($data['canonical_host'])->toBe('qmenu.kz')
        ->and($data['og_image_url'])->toBeNull();
});

it('caches the payload and drops it when settings change', function () {
    SeoSetting::current()->update(['title_ru' => 'Первый']);

    // Warm the cache.
    expect(PublicSeo::all()['ru']['title'])->toBe('Первый');

    SeoSetting::current()->update(['title_ru' => 'Второй']);

    // The model event cleared the cache.
    expect(PublicSeo::all()['ru']['title'])->toBe('Второй');
});

it('lets an admin edit the settings from the panel', function () {
    $admin = User::factory()->create(['is_admin' => true]);
    Filament::setCurrentPanel('admin');
    $this->actingAs($admin);

    Livewire::test(SeoSettings::class)
        ->fillForm([
            'title_ru' => 'Из панели',
            'description_kk' => 'Панельден',
        ])
        ->call('save')
        ->assertHasNoFormErrors();

    expect(SeoSetting::current()->title_ru)->toBe('Из панели')
        ->and(SeoSetting::current()->description_kk)->toBe('Панельден');
});

it('is a singleton — current() always returns one row', function () {
    SeoSetting::current();
    SeoSetting::current();

    expect(SeoSetting::query()->count())->toBe(1);
});
