<?php

namespace Dll\Resizing;

/**
 * Changing the size of an image for Resizing: resize, fit, cover, pad, crop.
 *
 * Every function takes a GdImage and returns a new one; the original is not changed.
 *
 * @category _("Image Handling class")
 */
class Size
{
    /**
     * Scale to exactly width × height (the shape may change).
     *
     * @param \GdImage  $image
     * @param int|float $width
     * @param int|float $height
     * @return \GdImage
     * @throws \SystemException When width or height is below 1
     */
    public static function resize(\GdImage $image, $width, $height): \GdImage
    {
        $width = (int) round($width);
        $height = (int) round($height);
        if ($width < 1 || $height < 1) {
            throw new \SystemException(_('Width and height must be at least 1'));
        }

        $new = Canvas::create($width, $height);
        imagecopyresampled($new, $image, 0, 0, 0, 0, $width, $height, imagesx($image), imagesy($image));

        return $new;
    }

    /**
     * Scale to fit inside width × height, keeping the shape.
     *
     * @param \GdImage $image
     * @param int      $width
     * @param int      $height
     * @param bool     $upscale Also make small images larger
     * @return \GdImage
     */
    public static function fit(\GdImage $image, int $width, int $height, bool $upscale = false): \GdImage
    {
        $ratio = min($width / imagesx($image), $height / imagesy($image));
        if ($ratio >= 1 && !$upscale) {
            return $image;
        }

        return self::resize($image, max(1, imagesx($image) * $ratio), max(1, imagesy($image) * $ratio));
    }

    /**
     * Fill exactly width × height: scale so it covers, then crop from the middle.
     *
     * @param \GdImage $image
     * @param int      $width
     * @param int      $height
     * @return \GdImage
     */
    public static function cover(\GdImage $image, int $width, int $height): \GdImage
    {
        $ratio = max($width / imagesx($image), $height / imagesy($image));
        $scaled = self::resize($image, max($width, imagesx($image) * $ratio), max($height, imagesy($image) * $ratio));

        return self::crop($scaled, $width, $height, intdiv(imagesx($scaled) - $width, 2), intdiv(imagesy($scaled) - $height, 2));
    }

    /**
     * Fit inside width × height and put it in the middle of a canvas of exactly that size.
     *
     * @param \GdImage     $image
     * @param int          $width
     * @param int          $height
     * @param string|array $color Background, see Canvas::create()
     * @return \GdImage
     */
    public static function pad(\GdImage $image, int $width, int $height, string|array $color = '#ffffff'): \GdImage
    {
        $fitted = self::fit($image, $width, $height, true);
        $canvas = Canvas::create($width, $height, $color);
        imagecopy($canvas, $fitted, intdiv($width - imagesx($fitted), 2), intdiv($height - imagesy($fitted), 2), 0, 0, imagesx($fitted), imagesy($fitted));

        return $canvas;
    }

    /**
     * Cut out width × height, starting at x, y.
     *
     * @param \GdImage $image
     * @param int      $width
     * @param int      $height
     * @param int      $x
     * @param int      $y
     * @return \GdImage
     * @throws \SystemException When width or height is below 1
     */
    public static function crop(\GdImage $image, int $width, int $height, int $x, int $y): \GdImage
    {
        if ($width < 1 || $height < 1) {
            throw new \SystemException(_('Width and height must be at least 1'));
        }

        $new = Canvas::create($width, $height);
        imagecopy($new, $image, 0, 0, max(0, $x), max(0, $y), $width, $height);

        return $new;
    }

    /**
     * Copy from x, y of the image to x1, y1 of a new canvas (older smartCrop()).
     *
     * @return \GdImage
     */
    public static function place(\GdImage $image, int $width, int $height, int $x, int $y, int $x1, int $y1): \GdImage
    {
        $new = Canvas::create($width, $height);
        imagecopy($new, $image, $x1, $y1, $x, $y, imagesx($image), imagesy($image));

        return $new;
    }
}
