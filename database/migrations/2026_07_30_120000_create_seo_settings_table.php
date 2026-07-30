<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Site-wide SEO settings for the public landing, editable from /admin.
 *
 * A singleton: exactly one row (id = 1) holds the whole configuration. The
 * landing reads it through a cached public endpoint (see PublicSeo) and Next.js
 * revalidates hourly, so an edit propagates without a redeploy. Bilingual
 * (RU + KK) title/description/keywords, a shared OG image, plus robots controls
 * (noindex) and a canonical host override.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('seo_settings', function (Blueprint $table) {
            $table->id();

            // Per-locale meta. Nullable throughout: an empty field means "fall
            // back to the built-in copy in landing.ts" on the front end.
            $table->string('title_ru')->nullable();
            $table->string('title_kk')->nullable();
            $table->text('description_ru')->nullable();
            $table->text('description_kk')->nullable();
            $table->text('keywords_ru')->nullable();
            $table->text('keywords_kk')->nullable();

            // Shared Open Graph / Twitter card image (relative path on the
            // public disk; absolute URL is built on read).
            $table->string('og_image_path')->nullable();

            // Robots: when true the landing emits noindex and robots.txt
            // disallows crawling. Off by default — the site wants to be found.
            $table->boolean('noindex')->default(false);

            // Canonical host override (e.g. "qmenu.kz"). Null = derive from the
            // deploy env as before. Lets the admin pin canonical without a
            // redeploy if the domain ever changes.
            $table->string('canonical_host')->nullable();

            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('seo_settings');
    }
};
