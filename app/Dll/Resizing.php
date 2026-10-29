<?php

use Dll\Resizing\Canvas;
use Dll\Resizing\Effect;
use Dll\Resizing\Format;
use Dll\Resizing\Size;

/**
 * Resize, crop and convert images (GD).
 *
 * <code>
 * (new Resizing())->load($_FILES['photo']['tmp_name'])
 *     ->fit(1600, 1600)                       // at most 1600×1600, keeps the shape
 *     ->save('upload/photos/42.webp', null, 80);
 *
 * (new Resizing())->load('upload/photos/42.webp')
 *     ->cover(300, 300)                       // exactly 300×300, cropped from the middle
 *     ->save('upload/thumbs/42.jpg');
 *
 * $logo = (new Resizing())->load('res/logo.png')->pad(200, 200, '#ffffff')->toDataUri();
 * </code>
 *
 * The work is done in small classes that are only loaded when they are used:
 * Resizing\Format (read and write files), Resizing\Size (resize, fit, cover,
 * pad, crop), Resizing\Effect (rotate, flip, grayscale, watermark) and
 * Resizing\Canvas (new canvases and colours).
 *
 * - Reads and writes JPEG, PNG, GIF, WebP, AVIF and BMP (what this PHP's GD supports).
 * - save() picks the type from the file extension and only writes inside the project.
 * - Transparency is kept for PNG, GIF, WebP and AVIF; when saving as JPEG it is put on white.
 * - Photos from phones are turned the right way (EXIF orientation) when loaded.
 * - Images over 40 megapixels are refused before they are decoded, so a small
 *   file cannot make PHP use gigabytes of memory (change with setMaxPixels()).
 *
 * @category _("Image Handling class")
 */
class Resizing
{
    /** @var \GdImage|null The image */
    private $image = null;

    /** @var int IMAGETYPE_* of the loaded file */
    private $image_type = IMAGETYPE_JPEG;

    /** @var int Largest image (width × height) that load() accepts */
    private int $maxPixels = 40_000_000;

    /**
     * Largest image (width × height) that load() accepts.
     *
     * @param int $pixels
     * @return $this
     */
    public function setMaxPixels(int $pixels)
    {
        $this->maxPixels = max(1, $pixels);
        return $this;
    }

    /**
     * Load an image file (also an upload's tmp_name, which is outside the project).
     *
     * @param string $filename
     * @return $this
     * @throws SystemException When it is not a supported image, or too large
     */
    public function load($filename)
    {
        $info = is_file($filename) ? @getimagesize($filename) : false;
        if ($info === false) {
            throw new SystemException(sprintf(_('Not an image: %s'), basename((string) $filename)));
        }

        [$width, $height, $type] = $info;
        if ($width * $height > $this->maxPixels) {
            throw new SystemException(sprintf(_('The image is too large (%d × %d pixels)'), $width, $height));
        }

        $this->image = Format::read($filename, $type);
        $this->image_type = $type;

        if ($type === IMAGETYPE_JPEG) {
            $this->image = Effect::orientation($this->image, $filename);
        }

        return $this;
    }

    /**
     * Start from a plain canvas instead of a file.
     *
     * @param int          $width
     * @param int          $height
     * @param string|array $color '#rrggbb', '#rgb', [r, g, b] or 'transparent'
     * @return $this
     */
    public function create(int $width, int $height, string|array $color = '#ffffff')
    {
        $this->image = Canvas::create($width, $height, $color);
        $this->image_type = IMAGETYPE_PNG;
        return $this;
    }

    /** @return int Width in pixels */
    public function getWidth()
    {
        return imagesx($this->gd());
    }

    /** @return int Height in pixels */
    public function getHeight()
    {
        return imagesy($this->gd());
    }

    /** @return int IMAGETYPE_* of the loaded file */
    public function getType(): int
    {
        return $this->image_type;
    }

    /** @return string Mime type of the loaded file */
    public function getMime(): string
    {
        return image_type_to_mime_type($this->image_type);
    }

    /**
     * Scale to exactly this size (the shape may change).
     *
     * @param int|float $width
     * @param int|float $height
     * @return $this
     */
    public function resize($width, $height)
    {
        $this->image = Size::resize($this->gd(), $width, $height);
        return $this;
    }

    /**
     * Scale to this width, height follows.
     *
     * @param int $width
     * @return $this
     */
    public function resizeToWidth($width)
    {
        return $this->resize($width, $this->getHeight() * $width / $this->getWidth());
    }

    /**
     * Scale to this height, width follows.
     *
     * @param int $height
     * @return $this
     */
    public function resizeToHeight($height)
    {
        return $this->resize($this->getWidth() * $height / $this->getHeight(), $height);
    }

    /**
     * Scale by a percentage (50 = half the size).
     *
     * @param int|float $scale
     * @return $this
     */
    public function scale($scale)
    {
        return $this->resize($this->getWidth() * $scale / 100, $this->getHeight() * $scale / 100);
    }

    /**
     * Scale to fit inside width × height, keeping the shape.
     *
     * @param int  $width
     * @param int  $height
     * @param bool $upscale Also make small images larger (default: no)
     * @return $this
     */
    public function fit(int $width, int $height, bool $upscale = false)
    {
        $this->image = Size::fit($this->gd(), $width, $height, $upscale);
        return $this;
    }

    /**
     * Fill exactly width × height: scale so it covers, then crop from the middle.
     *
     * @param int $width
     * @param int $height
     * @return $this
     */
    public function cover(int $width, int $height)
    {
        $this->image = Size::cover($this->gd(), $width, $height);
        return $this;
    }

    /**
     * Fit inside width × height and put it in the middle of a canvas of exactly that size.
     *
     * @param int          $width
     * @param int          $height
     * @param string|array $color Background: '#rrggbb', [r, g, b] or 'transparent'
     * @return $this
     */
    public function pad(int $width, int $height, string|array $color = '#ffffff')
    {
        $this->image = Size::pad($this->gd(), $width, $height, $color);
        return $this;
    }

    /**
     * Older name: fit inside width × height on a background of R, G, B.
     *
     * @return $this
     */
    public function smartResize($width, $height, $R, $G, $B)
    {
        return $this->pad((int) $width, (int) $height, [(int) $R, (int) $G, (int) $B]);
    }

    /**
     * Thumbnail: the longest side becomes $size.
     *
     * @param int $size
     * @return $this
     */
    public function thumbnails($size)
    {
        return $this->fit((int) $size, (int) $size, true);
    }

    /**
     * Older name for resize().
     *
     * @return $this
     */
    public function thumbnailsTo($width, $height)
    {
        return $this->resize($width, $height);
    }

    /**
     * Cut out width × height, starting at x, y.
     *
     * @param int $width
     * @param int $height
     * @param int $x
     * @param int $y
     * @return $this
     */
    public function crop($width, $height, $x, $y)
    {
        $this->image = Size::crop($this->gd(), (int) $width, (int) $height, (int) $x, (int) $y);
        return $this;
    }

    /**
     * Older name: copy from x, y to x1, y1 on a new width × height canvas.
     *
     * @return $this
     */
    public function smartCrop($width, $height, $x, $y, $x1, $y1)
    {
        $this->image = Size::place($this->gd(), (int) $width, (int) $height, (int) $x, (int) $y, (int) $x1, (int) $y1);
        return $this;
    }

    /**
     * Turn the image (counter-clockwise, like imagerotate()).
     *
     * @param int|float $degrees
     * @return $this
     */
    public function rotate($degrees = 0)
    {
        $this->image = Effect::rotate($this->gd(), $degrees);
        return $this;
    }

    /**
     * Mirror the image.
     *
     * @param string $direction 'horizontal' (left-right), 'vertical' or 'both'
     * @return $this
     */
    public function flip(string $direction = 'horizontal')
    {
        Effect::flip($this->gd(), $direction);
        return $this;
    }

    /**
     * Make the image black and white.
     *
     * @return $this
     */
    public function grayscale()
    {
        Effect::grayscale($this->gd());
        return $this;
    }

    /**
     * Replace the image with a plain colour.
     *
     * @param int $g Red (older parameter order)
     * @param int $r Green
     * @param int $b Blue
     * @param int $width
     * @param int $height
     * @return $this
     */
    public function backgroundcolor($g, $r, $b, $width, $height)
    {
        $this->image = Canvas::create((int) $width, (int) $height, [(int) $g, (int) $r, (int) $b]);
        return $this;
    }

    /**
     * Put a stamp (PNG) on the image.
     *
     * @param string|int  $y         'top', 'bottom' or pixels from the bottom
     * @param string|int  $x         'left', 'right' or pixels from the right
     * @param string|null $stampFile PNG (default: config::get('stamp'))
     * @param int         $margin    Space from the edge in pixels
     * @return $this
     */
    public function watermark($y, $x, ?string $stampFile = null, int $margin = 10)
    {
        Effect::watermark($this->gd(), $stampFile ?? (string) config::get('stamp', true), $y, $x, $margin);
        return $this;
    }

    /**
     * Save the image inside the project.
     *
     * @param string   $filename    Path from the project folder; the extension picks the type
     * @param int|null $image_type  IMAGETYPE_* (null: from the extension, else the loaded type)
     * @param int      $compression Quality 0–100 for JPEG, WebP and AVIF; for PNG 0–9 (default 6)
     * @param int|null $permissions For example 0644
     * @return $this
     * @throws \RuntimeException When the path is outside the project
     * @throws SystemException When the type is not supported or writing fails
     */
    public function save($filename, $image_type = null, $compression = 75, $permissions = null)
    {
        $path = Filesystem::resolve((string) $filename);
        $type = $image_type ?? Format::fromExtension($path, $this->image_type);

        if (!is_dir(dirname($path)) && !mkdir(dirname($path), 0775, true) && !is_dir(dirname($path))) {
            throw new SystemException(sprintf(_('Could not create the folder %s'), dirname($path)));
        }

        if (!Format::write($this->gd(), $type, $path, (int) $compression)) {
            throw new SystemException(sprintf(_('Could not save the image %s'), $filename));
        }
        if ($permissions !== null) {
            chmod($path, $permissions);
        }

        return $this;
    }

    /**
     * Send the image to the browser, with its Content-Type.
     *
     * @param int $image_type  IMAGETYPE_*
     * @param int $compression See save()
     * @return void
     */
    public function output($image_type = IMAGETYPE_JPEG, $compression = 75)
    {
        if (!headers_sent()) {
            header('Content-Type: ' . image_type_to_mime_type($image_type));
        }
        Format::write($this->gd(), $image_type, null, (int) $compression);
    }

    /**
     * The image as a data: URI, for small images in HTML or CSS.
     *
     * @param int $image_type  IMAGETYPE_* (default: PNG)
     * @param int $compression See save()
     * @return string
     */
    public function toDataUri($image_type = IMAGETYPE_PNG, $compression = 75): string
    {
        ob_start();
        Format::write($this->gd(), $image_type, null, (int) $compression);
        return 'data:' . image_type_to_mime_type($image_type) . ';base64,' . base64_encode((string) ob_get_clean());
    }

    /**
     * The loaded image.
     *
     * @return \GdImage
     * @throws SystemException When nothing is loaded
     */
    private function gd(): \GdImage
    {
        if (!$this->image instanceof \GdImage) {
            throw new SystemException(_('Load an image first: load() or create()'));
        }
        return $this->image;
    }
}
