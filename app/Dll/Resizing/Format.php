<?php

namespace Dll\Resizing;

/**
 * Reading and writing image files for Resizing: JPEG, PNG, GIF, WebP, AVIF, BMP.
 *
 * What works depends on the GD that PHP was built with; supports() tells.
 *
 * @category _("Image Handling class")
 */
class Format
{
    /** IMAGETYPE_AVIF (PHP 8.1+) */
    public const AVIF = 19;

    /** @var array<int, array{0: string, 1: string}> IMAGETYPE_* => [read function, write function] */
    private const FUNCTIONS = [
        IMAGETYPE_JPEG => ['imagecreatefromjpeg', 'imagejpeg'],
        IMAGETYPE_PNG => ['imagecreatefrompng', 'imagepng'],
        IMAGETYPE_GIF => ['imagecreatefromgif', 'imagegif'],
        IMAGETYPE_WEBP => ['imagecreatefromwebp', 'imagewebp'],
        IMAGETYPE_BMP => ['imagecreatefrombmp', 'imagebmp'],
        self::AVIF => ['imagecreatefromavif', 'imageavif'],
    ];

    /** @var array<string, int> File extension => IMAGETYPE_* */
    private const EXTENSIONS = [
        'jpg' => IMAGETYPE_JPEG, 'jpeg' => IMAGETYPE_JPEG, 'png' => IMAGETYPE_PNG, 'gif' => IMAGETYPE_GIF,
        'webp' => IMAGETYPE_WEBP, 'bmp' => IMAGETYPE_BMP, 'avif' => self::AVIF,
    ];

    /**
     * Whether this PHP can read and write the type.
     *
     * @param int $type IMAGETYPE_*
     * @return bool
     */
    public static function supports(int $type): bool
    {
        $functions = self::FUNCTIONS[$type] ?? null;

        return $functions !== null && function_exists($functions[0]) && function_exists($functions[1]);
    }

    /**
     * The type for a file name's extension.
     *
     * @param string   $filename
     * @param int|null $default Returned for an unknown extension
     * @return int|null IMAGETYPE_*
     */
    public static function fromExtension(string $filename, ?int $default = null): ?int
    {
        return self::EXTENSIONS[strtolower(pathinfo($filename, PATHINFO_EXTENSION))] ?? $default;
    }

    /**
     * Read an image file.
     *
     * @param string $filename
     * @param int    $type IMAGETYPE_* (from getimagesize())
     * @return \GdImage
     * @throws \SystemException When the type is not supported or the file cannot be read
     */
    public static function read(string $filename, int $type): \GdImage
    {
        self::check($type);

        $image = @(self::FUNCTIONS[$type][0])($filename);
        if (!$image instanceof \GdImage) {
            throw new \SystemException(sprintf(_('Could not read the image %s'), basename($filename)));
        }

        return $image;
    }

    /**
     * Write an image to a file, or to the output when $path is null.
     *
     * JPEG and BMP have no transparency: the image is put on white first.
     *
     * @param \GdImage    $image
     * @param int         $type    IMAGETYPE_*
     * @param string|null $path
     * @param int         $quality 0–100 for JPEG, WebP and AVIF; 0–9 for PNG (other values: 6)
     * @return bool
     * @throws \SystemException When the type is not supported
     */
    public static function write(\GdImage $image, int $type, ?string $path, int $quality = 75): bool
    {
        self::check($type);
        $write = self::FUNCTIONS[$type][1];

        if ($type === IMAGETYPE_JPEG || $type === IMAGETYPE_BMP) {
            $flat = Canvas::create(imagesx($image), imagesy($image), '#ffffff');
            imagecopy($flat, $image, 0, 0, 0, 0, imagesx($image), imagesy($image));
            $image = $flat;
        } else {
            imagealphablending($image, false);
            imagesavealpha($image, true);
        }

        return match ($type) {
            IMAGETYPE_JPEG, IMAGETYPE_WEBP, self::AVIF => $write($image, $path, max(0, min(100, $quality))),
            IMAGETYPE_PNG => $write($image, $path, $quality >= 0 && $quality <= 9 ? $quality : 6),
            default => $write($image, $path),
        };
    }

    /**
     * @param int $type
     * @return void
     * @throws \SystemException
     */
    private static function check(int $type): void
    {
        if (!self::supports($type)) {
            throw new \SystemException(sprintf(_('The image type is not supported: %s'), image_type_to_mime_type($type)));
        }
    }
}
