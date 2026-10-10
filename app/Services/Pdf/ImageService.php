<?php

namespace App\Services\Pdf;

use App\Exceptions\ToolException;
use GdImage;

/**
 * Image loading, resizing and encoding with PHP's GD extension.
 */
class ImageService
{
    /**
     * Loads JPG/PNG/WEBP with EXIF orientation applied.
     */
    public function load(string $path): GdImage
    {
        $info = @getimagesize($path);

        if (! $info) {
            throw ToolException::make('unsupported_format', 'One of the images could not be read.');
        }

        if ((int) config('pdftoolkit.limits.max_image_pixels', 60_000_000) < $info[0] * $info[1]) {
            throw ToolException::make('file_too_large', 'One of the images has too many pixels.');
        }

        $image = match ($info[2]) {
            IMAGETYPE_JPEG => @imagecreatefromjpeg($path),
            IMAGETYPE_PNG => @imagecreatefrompng($path),
            IMAGETYPE_WEBP => function_exists('imagecreatefromwebp') ? @imagecreatefromwebp($path) : false,
            default => false,
        };

        if (! $image instanceof GdImage) {
            throw ToolException::make('unsupported_format', 'One of the images could not be read.');
        }

        imagepalettetotruecolor($image);
        imagealphablending($image, false);
        imagesavealpha($image, true);

        if ($info[2] === IMAGETYPE_JPEG && function_exists('exif_read_data')) {
            $image = $this->orient($image, (int) (@exif_read_data($path)['Orientation'] ?? 1));
        }

        return $image;
    }

    public function formatOf(string $path): string
    {
        return match (@getimagesize($path)[2] ?? null) {
            IMAGETYPE_PNG => 'png',
            IMAGETYPE_WEBP => 'webp',
            default => 'jpg',
        };
    }

    public function resize(GdImage $image, int $width, int $height): GdImage
    {
        $width = max(1, $width);
        $height = max(1, $height);

        if ($width === imagesx($image) && $height === imagesy($image)) {
            return $image;
        }

        $resized = imagecreatetruecolor($width, $height);
        imagealphablending($resized, false);
        imagesavealpha($resized, true);
        imagefill($resized, 0, 0, imagecolorallocatealpha($resized, 255, 255, 255, 127));
        imagecopyresampled($resized, $image, 0, 0, 0, 0, $width, $height, imagesx($image), imagesy($image));
        imagedestroy($image);

        return $resized;
    }

    public function save(GdImage $image, string $path, string $format, int $quality): string
    {
        $quality = max(1, min(100, $quality));

        switch ($format) {
            case 'png':
                if ($quality < 85 && ! $this->hasAlpha($image)) {
                    // Lossy palette reduction gives big savings on PNG photos and screenshots.
                    imagetruecolortopalette($image, true, 256);
                }
                imagepng($image, $path, 9);
                break;

            case 'webp':
                if (! function_exists('imagewebp')) {
                    throw ToolException::make('engine_unavailable', 'WEBP is not supported by this server.');
                }
                imagewebp($image, $path, $quality);
                break;

            default:
                $flat = $this->flatten($image);
                imageinterlace($flat, true); // progressive JPEG
                imagejpeg($flat, $path, $quality);

                if ($flat !== $image) {
                    imagedestroy($flat);
                }
        }

        return $path;
    }

    public function hasAlpha(GdImage $image): bool
    {
        $w = imagesx($image);
        $h = imagesy($image);
        $stepX = max(1, intdiv($w, 100));
        $stepY = max(1, intdiv($h, 100));

        for ($y = 0; $y < $h; $y += $stepY) {
            for ($x = 0; $x < $w; $x += $stepX) {
                if (((imagecolorat($image, $x, $y) >> 24) & 0x7F) > 0) {
                    return true;
                }
            }
        }

        return false;
    }

    /**
     * JPEG has no transparency: composite onto white.
     */
    private function flatten(GdImage $image): GdImage
    {
        if (! $this->hasAlpha($image)) {
            return $image;
        }

        $flat = imagecreatetruecolor(imagesx($image), imagesy($image));
        imagefill($flat, 0, 0, imagecolorallocate($flat, 255, 255, 255));
        imagealphablending($flat, true);
        imagecopy($flat, $image, 0, 0, 0, 0, imagesx($image), imagesy($image));

        return $flat;
    }

    private function orient(GdImage $image, int $orientation): GdImage
    {
        $result = match ($orientation) {
            2 => $this->flip($image, IMG_FLIP_HORIZONTAL),
            3 => imagerotate($image, 180, 0),
            4 => $this->flip($image, IMG_FLIP_VERTICAL),
            5 => $this->flip(imagerotate($image, -90, 0), IMG_FLIP_HORIZONTAL),
            6 => imagerotate($image, -90, 0),
            7 => $this->flip(imagerotate($image, 90, 0), IMG_FLIP_HORIZONTAL),
            8 => imagerotate($image, 90, 0),
            default => $image,
        };

        return $result instanceof GdImage ? $result : $image;
    }

    private function flip(GdImage $image, int $mode): GdImage
    {
        imageflip($image, $mode);

        return $image;
    }
}
