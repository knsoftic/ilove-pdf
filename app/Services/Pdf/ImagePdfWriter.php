<?php

namespace App\Services\Pdf;

use App\Exceptions\ToolException;
use GdImage;

/**
 * Minimal, streaming PDF writer for "images to PDF".
 *
 * - JPEG files are embedded byte-for-byte (DCTDecode), so photos keep their
 *   exact quality and the PDF stays small. EXIF orientation is honoured with
 *   a transformation matrix instead of re-encoding.
 * - PNG files are embedded losslessly (Flate with PNG predictors); alpha
 *   channels become soft masks.
 * - Other formats (WEBP, interlaced/16-bit PNG) are normalised through GD.
 */
class ImagePdfWriter
{
    /** @var resource */
    private $handle;

    private int $objectCount = 2; // 1 = catalog, 2 = pages tree

    /** @var array<int, int> object number => byte offset */
    private array $offsets = [];

    /** @var list<int> */
    private array $pages = [];

    public function __construct(private readonly string $path)
    {
        $this->handle = fopen($path, 'wb');
        $this->write("%PDF-1.5\n%\xE2\xE3\xCF\xD3\n");
    }

    /**
     * Adds a page showing one image.
     *
     * @param  array{0: float, 1: float}|null  $pageSize  [width, height] in points, null = image size.
     */
    public function addImagePage(string $imagePath, ?array $pageSize, float $margin = 0.0): void
    {
        $image = $this->loadImage($imagePath);
        [$displayW, $displayH] = $image['rotated'] ? [$image['height'], $image['width']] : [$image['width'], $image['height']];

        // 96 DPI: one pixel = 0.75pt.
        $naturalW = $displayW * 0.75;
        $naturalH = $displayH * 0.75;

        if ($pageSize === null) {
            [$pageW, $pageH] = [$naturalW + 2 * $margin, $naturalH + 2 * $margin];
            [$drawW, $drawH] = [$naturalW, $naturalH];
        } else {
            [$pageW, $pageH] = $pageSize;
            $boxW = max(1, $pageW - 2 * $margin);
            $boxH = max(1, $pageH - 2 * $margin);
            $scale = min($boxW / $naturalW, $boxH / $naturalH);
            [$drawW, $drawH] = [$naturalW * $scale, $naturalH * $scale];
        }

        $x = ($pageW - $drawW) / 2;
        $y = ($pageH - $drawH) / 2;

        $imageObject = $this->writeImage($image);
        $matrix = $this->orientationMatrix($image['orientation'], $drawW, $drawH, $x, $y);
        $content = sprintf("q %s cm /Im0 Do Q\n", implode(' ', array_map(fn ($v) => $this->num($v), $matrix)));

        $contentObject = $this->newObject();
        $this->write("{$contentObject} 0 obj\n<< /Length ".strlen($content)." >>\nstream\n{$content}\nendstream\nendobj\n");

        $pageObject = $this->newObject();
        $this->write(sprintf(
            "%d 0 obj\n<< /Type /Page /Parent 2 0 R /MediaBox [0 0 %s %s] /Resources << /XObject << /Im0 %d 0 R >> /ProcSet [/PDF /ImageB /ImageC] >> /Contents %d 0 R >>\nendobj\n",
            $pageObject, $this->num($pageW), $this->num($pageH), $imageObject, $contentObject
        ));

        $this->pages[] = $pageObject;
    }

    public function close(): string
    {
        if ($this->pages === []) {
            throw ToolException::make('nothing_found');
        }

        $this->offsets[2] = ftell($this->handle);
        $kids = implode(' ', array_map(fn ($n) => "{$n} 0 R", $this->pages));
        $this->write("2 0 obj\n<< /Type /Pages /Kids [{$kids}] /Count ".count($this->pages)." >>\nendobj\n");

        $this->offsets[1] = ftell($this->handle);
        $this->write("1 0 obj\n<< /Type /Catalog /Pages 2 0 R >>\nendobj\n");

        $info = $this->newObject();
        $producer = $this->pdfString(setting('site_name', 'PDF Toolkit'));
        $this->write("{$info} 0 obj\n<< /Producer {$producer} /CreationDate (D:".gmdate('YmdHis')."Z) >>\nendobj\n");

        $xref = ftell($this->handle);
        $this->write("xref\n0 ".($this->objectCount + 1)."\n0000000000 65535 f \n");

        for ($i = 1; $i <= $this->objectCount; $i++) {
            $this->write(sprintf("%010d 00000 n \n", $this->offsets[$i] ?? 0));
        }

        $id = bin2hex(random_bytes(16));
        $this->write("trailer\n<< /Size ".($this->objectCount + 1)." /Root 1 0 R /Info {$info} 0 R /ID [<{$id}> <{$id}>] >>\nstartxref\n{$xref}\n%%EOF\n");
        fclose($this->handle);

        return $this->path;
    }

    /**
     * @return array{type: string, width: int, height: int, orientation: int, rotated: bool, data?: string, colorspace?: string, bpc?: int, decode?: string, parms?: string, smask?: string, palette?: string}
     */
    private function loadImage(string $path): array
    {
        $info = @getimagesize($path);

        if (! $info) {
            throw ToolException::make('unsupported_format', 'One of the images could not be read.');
        }

        if ((int) config('pdftoolkit.limits.max_image_pixels', 60_000_000) < $info[0] * $info[1]) {
            throw ToolException::make('file_too_large', 'One of the images has too many pixels.');
        }

        $orientation = 1;

        if ($info[2] === IMAGETYPE_JPEG && function_exists('exif_read_data')) {
            $exif = @exif_read_data($path);
            $orientation = (int) ($exif['Orientation'] ?? 1);
            $orientation = $orientation >= 1 && $orientation <= 8 ? $orientation : 1;
        }

        $base = ['width' => $info[0], 'height' => $info[1], 'orientation' => $orientation, 'rotated' => $orientation >= 5];

        if ($info[2] === IMAGETYPE_JPEG) {
            $channels = $info['channels'] ?? 3;
            $data = (string) file_get_contents($path);
            $colorspace = match ($channels) {
                1 => '/DeviceGray',
                4 => '/DeviceCMYK',
                default => '/DeviceRGB',
            };

            return $base + [
                'type' => 'jpeg',
                'data' => $data,
                'colorspace' => $colorspace,
                'bpc' => 8,
                // Adobe CMYK JPEGs are stored inverted.
                'decode' => $channels === 4 && str_contains(substr($data, 0, 4096), 'Adobe') ? ' /Decode [1 0 1 0 1 0 1 0]' : '',
            ];
        }

        if ($info[2] === IMAGETYPE_PNG && ($png = $this->parsePng($path))) {
            return $base + $png;
        }

        // WEBP or exotic PNG: normalise through GD.
        return $this->fromGd($path, $info[2]) + $base;
    }

    private function fromGd(string $path, int $type): array
    {
        $gd = match ($type) {
            IMAGETYPE_WEBP => function_exists('imagecreatefromwebp') ? @imagecreatefromwebp($path) : false,
            IMAGETYPE_PNG => @imagecreatefrompng($path),
            IMAGETYPE_GIF => @imagecreatefromgif($path),
            default => false,
        };

        if (! $gd instanceof GdImage) {
            throw ToolException::make('unsupported_format', 'One of the images could not be read.');
        }

        imagepalettetotruecolor($gd);
        $tmp = $path.'.normalised';

        if ($this->hasTransparency($gd)) {
            imagealphablending($gd, false);
            imagesavealpha($gd, true);
            imageinterlace($gd, false);
            imagepng($gd, $tmp.'.png', 6);
            imagedestroy($gd);
            $png = $this->parsePng($tmp.'.png');
            @unlink($tmp.'.png');

            if (! $png) {
                throw ToolException::make('conversion_failed');
            }

            return $png + ['width' => $png['width'], 'height' => $png['height']];
        }

        imageinterlace($gd, false);
        imagejpeg($gd, $tmp.'.jpg', 92);
        imagedestroy($gd);
        $data = (string) file_get_contents($tmp.'.jpg');
        @unlink($tmp.'.jpg');

        return ['type' => 'jpeg', 'data' => $data, 'colorspace' => '/DeviceRGB', 'bpc' => 8, 'decode' => ''];
    }

    private function hasTransparency(GdImage $gd): bool
    {
        $w = imagesx($gd);
        $h = imagesy($gd);
        $stepX = max(1, intdiv($w, 120));
        $stepY = max(1, intdiv($h, 120));

        for ($y = 0; $y < $h; $y += $stepY) {
            for ($x = 0; $x < $w; $x += $stepX) {
                if (((imagecolorat($gd, $x, $y) >> 24) & 0x7F) > 0) {
                    return true;
                }
            }
        }

        return false;
    }

    /**
     * Parses a non-interlaced 8-bit PNG so its compressed data can be
     * embedded directly. Returns null for files that need GD.
     */
    private function parsePng(string $path): ?array
    {
        $f = fopen($path, 'rb');
        fread($f, 8);
        $width = $height = $bpc = $colorType = $interlace = 0;
        $palette = '';
        $trns = null;
        $data = '';

        while (! feof($f)) {
            $lengthBytes = fread($f, 4);

            if (strlen($lengthBytes) < 4) {
                break;
            }

            $length = unpack('N', $lengthBytes)[1];
            $type = fread($f, 4);
            $chunk = $length > 0 ? fread($f, $length) : '';
            fread($f, 4); // CRC

            switch ($type) {
                case 'IHDR':
                    $h = unpack('Nwidth/Nheight/Cbpc/Ccolor/Ccompression/Cfilter/Cinterlace', $chunk);
                    [$width, $height, $bpc, $colorType, $interlace] = [$h['width'], $h['height'], $h['bpc'], $h['color'], $h['interlace']];
                    break;
                case 'PLTE':
                    $palette = $chunk;
                    break;
                case 'tRNS':
                    $trns = $chunk;
                    break;
                case 'IDAT':
                    $data .= $chunk;
                    break;
                case 'IEND':
                    break 2;
            }
        }

        fclose($f);

        if ($bpc !== 8 || $interlace !== 0 || ($colorType === 3 && $trns !== null) || ($trns !== null && $colorType !== 3)) {
            return null;
        }

        $colors = match ($colorType) {
            0, 4 => 1,
            2, 6 => 3,
            3 => 1,
            default => null,
        };

        if ($colors === null) {
            return null;
        }

        $colorspace = match ($colorType) {
            0, 4 => '/DeviceGray',
            3 => '[/Indexed /DeviceRGB '.(strlen($palette) / 3 - 1).' <'.bin2hex($palette).'>]',
            default => '/DeviceRGB',
        };

        $image = [
            'type' => 'png',
            'width' => $width,
            'height' => $height,
            'colorspace' => $colorspace,
            'bpc' => 8,
            'decode' => '',
        ];

        if ($colorType === 4 || $colorType === 6) {
            // Split colour and alpha channels into two predictor-free streams.
            $raw = @gzuncompress($data);

            if ($raw === false) {
                return null;
            }

            [$color, $alpha] = $this->splitAlpha($raw, $width, $height, $colorType === 6 ? 3 : 1);
            $image['data'] = gzcompress($color, 6);
            $image['smask'] = gzcompress($alpha, 6);
            $image['parms'] = '';
        } else {
            $image['data'] = $data;
            $image['parms'] = "/DecodeParms << /Predictor 15 /Colors {$colors} /BitsPerComponent 8 /Columns {$width} >>";
        }

        return $image;
    }

    /**
     * Undoes PNG row filters and separates colour bytes from alpha bytes.
     */
    private function splitAlpha(string $raw, int $width, int $height, int $colorChannels): array
    {
        $bpp = $colorChannels + 1;
        $stride = $width * $bpp;
        $color = '';
        $alpha = '';
        $previous = str_repeat("\0", $stride);
        $offset = 0;

        for ($y = 0; $y < $height; $y++) {
            $filter = ord($raw[$offset]);
            $line = substr($raw, $offset + 1, $stride);
            $offset += $stride + 1;
            $line = $this->unfilter($filter, $line, $previous, $bpp);
            $previous = $line;

            // Fast channel split using regular expressions over fixed-size groups.
            if ($colorChannels === 3) {
                $color .= preg_replace('/(.{3})./s', '$1', $line);
                $alpha .= preg_replace('/.{3}(.)/s', '$1', $line);
            } else {
                $color .= preg_replace('/(.)./s', '$1', $line);
                $alpha .= preg_replace('/.(.)/s', '$1', $line);
            }
        }

        return [$color, $alpha];
    }

    private function unfilter(int $filter, string $line, string $previous, int $bpp): string
    {
        if ($filter === 0) {
            return $line;
        }

        $length = strlen($line);
        $out = $line;

        for ($i = 0; $i < $length; $i++) {
            $a = $i >= $bpp ? ord($out[$i - $bpp]) : 0;
            $b = ord($previous[$i]);
            $c = $i >= $bpp ? ord($previous[$i - $bpp]) : 0;
            $x = ord($line[$i]);

            $value = match ($filter) {
                1 => $x + $a,
                2 => $x + $b,
                3 => $x + intdiv($a + $b, 2),
                4 => $x + $this->paeth($a, $b, $c),
                default => $x,
            };

            $out[$i] = chr($value & 0xFF);
        }

        return $out;
    }

    private function paeth(int $a, int $b, int $c): int
    {
        $p = $a + $b - $c;
        $pa = abs($p - $a);
        $pb = abs($p - $b);
        $pc = abs($p - $c);

        return ($pa <= $pb && $pa <= $pc) ? $a : ($pb <= $pc ? $b : $c);
    }

    private function writeImage(array $image): int
    {
        $smaskRef = '';

        if (! empty($image['smask'])) {
            $smask = $this->newObject();
            $this->write(sprintf(
                "%d 0 obj\n<< /Type /XObject /Subtype /Image /Width %d /Height %d /ColorSpace /DeviceGray /BitsPerComponent 8 /Filter /FlateDecode /Length %d >>\nstream\n",
                $smask, $image['width'], $image['height'], strlen($image['smask'])
            ));
            $this->write($image['smask']."\nendstream\nendobj\n");
            $smaskRef = " /SMask {$smask} 0 R";
        }

        $object = $this->newObject();
        $filter = $image['type'] === 'jpeg' ? '/DCTDecode' : '/FlateDecode';

        $this->write(sprintf(
            "%d 0 obj\n<< /Type /XObject /Subtype /Image /Width %d /Height %d /ColorSpace %s /BitsPerComponent %d /Filter %s %s%s%s /Length %d >>\nstream\n",
            $object, $image['width'], $image['height'], $image['colorspace'], $image['bpc'], $filter,
            $image['parms'] ?? '', $image['decode'] ?? '', $smaskRef, strlen($image['data'])
        ));
        $this->write($image['data']."\nendstream\nendobj\n");

        return $object;
    }

    /**
     * Matrix that draws the stored image with its EXIF orientation applied
     * into the box (x, y, w, h).
     *
     * @return array{float, float, float, float, float, float}
     */
    private function orientationMatrix(int $orientation, float $w, float $h, float $x, float $y): array
    {
        return match ($orientation) {
            2 => [-$w, 0, 0, $h, $x + $w, $y],
            3 => [-$w, 0, 0, -$h, $x + $w, $y + $h],
            4 => [$w, 0, 0, -$h, $x, $y + $h],
            5 => [0, -$h, -$w, 0, $x + $w, $y + $h],
            6 => [0, -$h, $w, 0, $x, $y + $h],
            7 => [0, $h, $w, 0, $x, $y],
            8 => [0, $h, -$w, 0, $x + $w, $y],
            default => [$w, 0, 0, $h, $x, $y],
        };
    }

    private function newObject(): int
    {
        $this->objectCount++;
        $this->offsets[$this->objectCount] = ftell($this->handle);

        return $this->objectCount;
    }

    private function write(string $data): void
    {
        fwrite($this->handle, $data);
    }

    private function num(float|int $value): string
    {
        return rtrim(rtrim(sprintf('%.4F', $value), '0'), '.') ?: '0';
    }

    private function pdfString(string $value): string
    {
        return '('.strtr(preg_replace('/[^\x20-\x7E]/', '', $value) ?? '', ['\\' => '\\\\', '(' => '\\(', ')' => '\\)']).')';
    }
}
