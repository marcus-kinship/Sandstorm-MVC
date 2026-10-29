<?php

namespace Dll\Resizing;

/**
 * Effects for Resizing: rotate, flip, grayscale, watermark, EXIF orientation.
 *
 * @category _("Image Handling class")
 */
class Effect
{
    /**
     * Turn the image (counter-clockwise, like imagerotate()); new corners are transparent.
     *
     * @param \GdImage  $image
     * @param int|float $degrees
     * @return \GdImage
     */
    public static function rotate(\GdImage $image, $degrees): \GdImage
    {
        $rotated = imagerotate($image, $degrees, imagecolorallocatealpha($image, 0, 0, 0, 127));
        imagesavealpha($rotated, true);

        return $rotated;
    }

    /**
     * Mirror the image (changes it in place).
     *
     * @param \GdImage $image
     * @param string   $direction 'horizontal', 'vertical' (or 'v') or 'both'
     * @return \GdImage
     */
    public static function flip(\GdImage $image, string $direction = 'horizontal'): \GdImage
    {
        imageflip($image, match ($direction) {
            'vertical', 'v' => IMG_FLIP_VERTICAL,
            'both' => IMG_FLIP_BOTH,
            default => IMG_FLIP_HORIZONTAL,
        });

        return $image;
    }

    /**
     * Black and white (changes it in place).
     *
     * @param \GdImage $image
     * @return \GdImage
     */
    public static function grayscale(\GdImage $image): \GdImage
    {
        imagefilter($image, IMG_FILTER_GRAYSCALE);
        return $image;
    }

    /**
     * Put a PNG stamp on the image (changes it in place).
     *
     * @param \GdImage   $image
     * @param string     $stampFile PNG file
     * @param string|int $y         'top', 'bottom' or pixels from the bottom
     * @param string|int $x         'left', 'right' or pixels from the right
     * @param int        $margin    Space from the edge for top/bottom/left/right
     * @return \GdImage
     * @throws \SystemException For a missing stamp or an invalid position
     */
    public static function watermark(\GdImage $image, string $stampFile, $y, $x, int $margin = 10): \GdImage
    {
        $stamp = $stampFile !== '' && is_file($stampFile) ? @imagecreatefrompng($stampFile) : false;
        if (!$stamp instanceof \GdImage) {
            throw new \SystemException(sprintf(_('Could not read the watermark %s'), $stampFile));
        }

        $sx = imagesx($stamp);
        $sy = imagesy($stamp);

        $left = match (true) {
            $x === 'left' => $margin,
            $x === 'right' => imagesx($image) - $sx - $margin,
            is_numeric($x) => imagesx($image) - $sx - (int) $x,
            default => throw new \SystemException(sprintf(_('Invalid x position for the watermark: %s'), $x)),
        };
        $top = match (true) {
            $y === 'top' => $margin,
            $y === 'bottom' => imagesy($image) - $sy - $margin,
            is_numeric($y) => imagesy($image) - $sy - (int) $y,
            default => throw new \SystemException(sprintf(_('Invalid y position for the watermark: %s'), $y)),
        };

        imagealphablending($image, true);
        imagecopy($image, $stamp, $left, $top, 0, 0, $sx, $sy);

        return $image;
    }

    /**
     * Turn a JPEG the right way up from its EXIF orientation (photos from phones).
     *
     * @param \GdImage $image
     * @param string   $filename The JPEG file the image came from
     * @return \GdImage
     */
    public static function orientation(\GdImage $image, string $filename): \GdImage
    {
        if (!function_exists('exif_read_data')) {
            return $image;
        }

        return match ((int) (@exif_read_data($filename)['Orientation'] ?? 1)) {
            2 => self::flip($image, 'horizontal'),
            3 => self::rotate($image, 180),
            4 => self::flip($image, 'vertical'),
            5 => self::flip(self::rotate($image, -90), 'horizontal'),
            6 => self::rotate($image, -90),
            7 => self::flip(self::rotate($image, 90), 'horizontal'),
            8 => self::rotate($image, 90),
            default => $image,
        };
    }
}
