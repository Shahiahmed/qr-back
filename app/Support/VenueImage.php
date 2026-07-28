<?php

namespace App\Support;

use App\Models\Establishment;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/**
 * Processes and stores a venue's cover / logo upload.
 *
 * Uploads are downscaled and re-encoded to WebP with GD before they touch the
 * disk (the CLAUDE.md rule: shrink + resize, don't serve the raw phone photo).
 * The relative path is what gets saved on the model; the public URL is built
 * from it in the resource. A random filename per save doubles as cache-busting —
 * the guest's browser and any CDN see a new URL the instant a photo changes.
 */
class VenueImage
{
    /** The two slots an owner can fill, with the model column each maps to. */
    public const KINDS = [
        'cover' => 'cover_path',
        'logo' => 'logo_path',
    ];

    /** Long edge caps. A cover spans the screen; a logo is a small badge. */
    private const MAX_EDGE = [
        'cover' => 1600,
        'logo' => 512,
    ];

    /** WebP quality — visually clean, far smaller than the source JPEG/PNG. */
    private const QUALITY = 82;

    private const DISK = 'public';

    /**
     * Store a processed image for the venue, replacing any previous one in the
     * same slot, and persist the new path. Returns the stored relative path.
     */
    public static function store(Establishment $establishment, string $kind, UploadedFile $file): string
    {
        $column = self::KINDS[$kind];

        $binary = self::encode($file, $kind);

        // A logo keeps its transparency; a webp extension regardless of source.
        $path = "venues/{$establishment->id}/{$kind}-".Str::random(16).'.webp';
        Storage::disk(self::DISK)->put($path, $binary);

        self::deleteFile($establishment->{$column});

        // update() (not save-on-attribute) so the model's saved event fires and
        // the public menu cache is dropped — a stale cover is as wrong as a
        // stale price.
        $establishment->update([$column => $path]);

        return $path;
    }

    /** Remove the venue's image in this slot, if any, and null the column. */
    public static function remove(Establishment $establishment, string $kind): void
    {
        $column = self::KINDS[$kind];

        self::deleteFile($establishment->{$column});
        $establishment->update([$column => null]);
    }

    /** Public URL for a stored path, or null. Absolute via the disk's url. */
    public static function url(?string $path): ?string
    {
        return $path ? Storage::disk(self::DISK)->url($path) : null;
    }

    /**
     * Downscale to the slot's long-edge cap (never upscale) and encode WebP,
     * preserving alpha so a transparent logo stays transparent.
     */
    private static function encode(UploadedFile $file, string $kind): string
    {
        // imagecreatefromstring auto-detects JPEG/PNG/WebP/GIF — one path for all.
        $source = imagecreatefromstring((string) file_get_contents($file->getRealPath()));

        if ($source === false) {
            // Validation already guaranteed an image; guard the edge anyway.
            abort(422, 'Unsupported image.');
        }

        $width = imagesx($source);
        $height = imagesy($source);
        $cap = self::MAX_EDGE[$kind];

        $scale = min(1, $cap / max($width, $height));
        $newWidth = max(1, (int) round($width * $scale));
        $newHeight = max(1, (int) round($height * $scale));

        $canvas = imagecreatetruecolor($newWidth, $newHeight);
        imagealphablending($canvas, false);
        imagesavealpha($canvas, true);
        $transparent = imagecolorallocatealpha($canvas, 0, 0, 0, 127);
        imagefilledrectangle($canvas, 0, 0, $newWidth, $newHeight, $transparent);

        imagecopyresampled($canvas, $source, 0, 0, 0, 0, $newWidth, $newHeight, $width, $height);

        ob_start();
        imagewebp($canvas, null, self::QUALITY);
        $binary = (string) ob_get_clean();

        imagedestroy($source);
        imagedestroy($canvas);

        return $binary;
    }

    private static function deleteFile(?string $path): void
    {
        if ($path && Storage::disk(self::DISK)->exists($path)) {
            Storage::disk(self::DISK)->delete($path);
        }
    }
}
