<?php

namespace Dll\Resizing;

/**
 * New canvases and colours for Resizing.
 *
 * @category _("Image Handling class")
 */
class Canvas
{
    /**
     * A new true colour canvas filled with a colour.
     *
     * @param int          $width
     * @param int          $height
     * @param string|array $color '#rrggbb', '#rgb', [r, g, b] or 'transparent'
     * @return \GdImage
     * @throws \InvalidArgumentException For an invalid colour
     */
    public static function create(int $width, int $height, string|array $color = 'transparent'): \GdImage
    {
        $canvas = imagecreatetruecolor(max(1, $width), max(1, $height));
        imagealphablending($canvas, false);
        imagesavealpha($canvas, true);

        if ($color === 'transparent') {
            $fill = imagecolorallocatealpha($canvas, 0, 0, 0, 127);
        } else {
            [$r, $g, $b] = is_array($color) ? array_values($color) + [0, 0, 0] : self::hex($color);
            $fill = imagecolorallocate($canvas, (int) $r, (int) $g, (int) $b);
        }
        imagefilledrectangle($canvas, 0, 0, $width, $height, $fill);
        imagealphablending($canvas, true);

        return $canvas;
    }

    /**
     * '#rrggbb' or '#rgb' to [r, g, b].
     *
     * @param string $hex
     * @return array{0: int, 1: int, 2: int}
     * @throws \InvalidArgumentException
     */
    public static function hex(string $hex): array
    {
        $hex = ltrim($hex, '#');
        if (strlen($hex) === 3) {
            $hex = $hex[0] . $hex[0] . $hex[1] . $hex[1] . $hex[2] . $hex[2];
        }
        if (!preg_match('/^[0-9a-f]{6}$/i', $hex)) {
            throw new \InvalidArgumentException(sprintf(_('Invalid colour: %s'), $hex));
        }

        return [hexdec(substr($hex, 0, 2)), hexdec(substr($hex, 2, 2)), hexdec(substr($hex, 4, 2))];
    }
}
