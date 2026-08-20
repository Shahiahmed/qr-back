<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Promotional pop-up shown on the public landing, editable from /admin.
 *
 * A singleton (one row, id = 1), like seo_settings: the landing reads it through
 * a cached public endpoint (see PublicPromo) and Next.js revalidates hourly, so
 * an edit propagates without a redeploy. Bilingual (RU + KK) badge / title /
 * body / CTA label, a shared CTA url, an on/off switch and an optional schedule
 * window (starts_at / ends_at) so a campaign can be planned ahead.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('promo_settings', function (Blueprint $table) {
            $table->id();

            // Master switch. Off by default — a fresh install shows no pop-up.
            $table->boolean('enabled')->default(false);

            // Small accent chip, e.g. "−20%" / "Акция". Bilingual, nullable.
            $table->string('badge_ru')->nullable();
            $table->string('badge_kk')->nullable();

            // Headline and body. Nullable throughout; an empty title means the
            // pop-up is treated as having no content and stays hidden.
            $table->string('title_ru')->nullable();
            $table->string('title_kk')->nullable();
            $table->text('body_ru')->nullable();
            $table->text('body_kk')->nullable();

            // Call-to-action button. Label is per-locale; the url is shared.
            $table->string('cta_label_ru')->nullable();
            $table->string('cta_label_kk')->nullable();
            $table->string('cta_url')->nullable();

            // Optional schedule. Null start = live now; null end = no expiry.
            // Activeness is computed live against now() on every read, so the
            // window takes effect without waiting out the cache.
            $table->timestamp('starts_at')->nullable();
            $table->timestamp('ends_at')->nullable();

            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('promo_settings');
    }
};
