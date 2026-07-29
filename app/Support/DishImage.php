<?php

namespace App\Support;

use App\Models\Dish;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/**
 * Processes and stores a dish photo.
 *
 * A menu screen shows many dishes at once on a phone, so weight is the enemy:
 * every upload is centre-cropped to a square and re-encoded to WebP with GD
 * before it touches disk. The owner-facing cropper already sends a square, but
 * we crop again here so the guarantee holds no matter what reaches the endpoint
 * (CLAUDE.md rule: shrink + resize, never serve the raw phone photo).
 *
 * Mirrors VenueImage: the relative path is stored on the model, the public URL
 * is built in the resource, and a random filename per save busts caches.
 */
class DishImage
{
    /**
     * Square edge of the stored photo. The card thumbnail is tiny (~96px), but
     * we keep 800 so a retina card or a future larger view stays crisp — still
     * only ~30-60 KB as WebP.
     */
    private const EDGE = 800;

    /** WebP quality — visually clean, far smaller than the source JPEG. */
    private const QUALITY = 80;

    private const DISK = 'public';

    /**
     * Store a processed photo for the dish, replacing any previous one, and
     * persist the new path. Returns the stored relative path.
     */
    public static function store(Dish $dish, UploadedFile $file): string
    {
        $binary = self::encode($file);

        // Under the owning venue's folder → dishes/{id}, so every file of a
        // venue (cover, logo, each dish) sits together in one readable tree.
        $path = "{$dish->establishment->storageFolder()}/dishes/{$dish->id}/photo-".Str::random(16).'.webp';
        Storage::disk(self::DISK)->put($path, $binary);

        self::deleteFile($dish->image_path);

        // update() (not save-on-attribute) so the model's saved event fires and
        // the public menu cache is dropped — a stale photo is as wrong as a
        // stale price.
        $dish->update(['image_path' => $path]);

        return $path;
    }

    /** Remove the dish photo, if any, and null the column. */
    public static function remove(Dish $dish): void
    {
        self::deleteFile($dish->image_path);
        $dish->update(['image_path' => null]);
    }

    /** Public URL for a stored path, or null. Absolute via the disk's url. */
    public static function url(?string $path): ?string
    {
        return $path ? Storage::disk(self::DISK)->url($path) : null;
    }

    /**
     * Centre-crop to a square and downscale to EDGE (never upscale), then encode
     * WebP. Alpha is preserved so a transparent PNG stays clean.
     */
    private static function encode(UploadedFile $file): string
    {
        // imagecreatefromstring auto-detects JPEG/PNG/WebP/GIF — one path for all.
        $source = imagecreatefromstring((string) file_get_contents($file->getRealPath()));

        if ($source === false) {
            // Validation already guaranteed an image; guard the edge anyway.
            abort(422, 'Unsupported image.');
        }

        $width = imagesx($source);
        $height = imagesy($source);

        // The largest centred square that fits the source.
        $square = min($width, $height);
        $srcX = (int) (($width - $square) / 2);
        $srcY = (int) (($height - $square) / 2);

        // Output edge: the square, capped at EDGE (never upscale a small photo).
        $edge = min($square, self::EDGE);

        $canvas = imagecreatetruecolor($edge, $edge);
        imagealphablending($canvas, false);
        imagesavealpha($canvas, true);
        $transparent = imagecolorallocatealpha($canvas, 0, 0, 0, 127);
        imagefilledrectangle($canvas, 0, 0, $edge, $edge, $transparent);

        imagecopyresampled($canvas, $source, 0, 0, $srcX, $srcY, $edge, $edge, $square, $square);

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
