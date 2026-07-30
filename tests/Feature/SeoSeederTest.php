<?php

use App\Models\SeoSetting;
use Database\Seeders\SeoSeeder;

it('fills the empty SEO singleton with bilingual defaults', function () {
    $this->seed(SeoSeeder::class);

    $seo = SeoSetting::current();

    expect($seo->title_ru)->not->toBeEmpty()
        ->and($seo->title_kk)->not->toBeEmpty()
        ->and($seo->description_ru)->not->toBeEmpty()
        ->and($seo->description_kk)->not->toBeEmpty()
        ->and($seo->keywords_ru)->not->toBeEmpty()
        ->and($seo->keywords_kk)->not->toBeEmpty()
        // The seeder never invents an OG image — that is uploaded in the panel.
        ->and($seo->og_image_path)->toBeNull();
});

it('does not clobber admin-edited fields on a re-seed', function () {
    SeoSetting::current()->update([
        'title_ru' => 'Правка админа',
        'og_image_path' => 'seo/custom.webp',
    ]);

    $this->seed(SeoSeeder::class);

    $seo = SeoSetting::current();

    // Admin values survive; only the still-empty columns get defaults.
    expect($seo->title_ru)->toBe('Правка админа')
        ->and($seo->og_image_path)->toBe('seo/custom.webp')
        ->and($seo->title_kk)->not->toBeEmpty();
});
