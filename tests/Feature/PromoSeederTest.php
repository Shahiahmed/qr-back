<?php

use App\Models\PromoSetting;
use Database\Seeders\PromoSeeder;

it('fills the empty promo singleton with bilingual defaults, left disabled', function () {
    $this->seed(PromoSeeder::class);

    $promo = PromoSetting::current();

    expect($promo->title_ru)->not->toBeEmpty()
        ->and($promo->title_kk)->not->toBeEmpty()
        ->and($promo->body_ru)->not->toBeEmpty()
        ->and($promo->body_kk)->not->toBeEmpty()
        ->and($promo->cta_label_ru)->not->toBeEmpty()
        ->and($promo->cta_url)->not->toBeEmpty()
        // The promo must not go live from a seed — the admin flips it on.
        ->and($promo->enabled)->toBeFalse();
});

it('does not clobber admin-edited copy on a re-seed', function () {
    PromoSetting::current()->update([
        'enabled' => true,
        'title_ru' => 'Своя акция',
    ]);

    $this->seed(PromoSeeder::class);

    $promo = PromoSetting::current();

    // Edited fields survive; only still-empty columns get defaults; the live
    // toggle is never touched.
    expect($promo->title_ru)->toBe('Своя акция')
        ->and($promo->enabled)->toBeTrue()
        ->and($promo->title_kk)->not->toBeEmpty();
});
