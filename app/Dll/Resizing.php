<?php

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

    /** @var array<int, array{0: string, 1: string, 2: string}> type => [read function, write function, mime] */
    private const TYPES = [
        IMAGETYPE_JPEG => ['imagecreatefromjpeg', 'imagejpeg', 'image/jpeg'],
        IMAGETYPE_PNG => ['imagecreatefrompng', 'imagepng', 'image/png'],
        IMAGETYPE_GIF => ['imagecreatefromgif', 'imagegif', 'image/gif'],
        IMAGETYPE_WEBP => ['imagecreatefromwebp', 'imagewebp', 'image/webp'],
        IMAGETYPE_BMP => ['imagecreatefrombmp', 'imagebmp', 'image/bmp'],
    ];

    /** @var array<string, int> File extension => IMAGETYPE_* */
    private const EXTENSIONS = [
        'jpg' => IMAGETYPE_JPEG, 'jpeg' => IMAGETYPE_JPEG, 'png' => IMAGETYPE_PNG, 'gif' => IMAGETYPE_GIF,
        'webp' => IMAGETYPE_WEBP, 'bmp' => IMAGETYPE_BMP, 'avif' => 19,
    ];

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

        $read = $this->functions($type)[0];
        $image = @$read($filename);
        if (!$image instanceof \GdImage) {
            throw new SystemException(sprintf(_('Could not read the image %s'), basename((string) $filename)));
        }

        $this->image = $image;
        $this->image_type = $type;

        if ($type === IMAGETYPE_JPEG) {
            $this->fixOrientation($filename);
        }

        return $this;
    }

    /**
     * Start from a plain canvas instead of a file.
     *
     * @param int          $width
     * @param int          $height
     * @param string|array $color '#rrggbb', '#rgb' or [r, g, b]; 'transparent' for none
     * @return $this
     */
    public function create(int $width, int $height, string|array $color = '#ffffff')
    {
        $this->image = $this->canvas($width, $height, $color);
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
     * @throws SystemException When width or height is below 1
     */
    public function resize($width, $height)
    {
        $width = (int) round($width);
        $height = (int) round($height);
        if ($width < 1 || $height < 1) {
            throw new SystemException(_('Width and height must be at least 1'));
        }

        $new = $this->canvas($width, $height, 'transparent');
        imagecopyresampled($new, $this->gd(), 0, 0, 0, 0, $width, $height, $this->getWidth(), $this->getHeight());
        $this->image = $new;

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
        $ratio = min($width / $this->getWidth(), $height / $this->getHeight());
        if ($ratio >= 1 && !$upscale) {
            return $this;
        }

        return $this->resize(max(1, $this->getWidth() * $ratio), max(1, $this->getHeight() * $ratio));
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
        $ratio = max($width / $this->getWidth(), $height / $this->getHeight());
        $this->resize(max($width, $this->getWidth() * $ratio), max($height, $this->getHeight() * $ratio));

        return $this->crop($width, $height, intdiv($this->getWidth() - $width, 2), intdiv($this->getHeight() - $height, 2));
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
        $this->fit($width, $height, true);

        $canvas = $this->canvas($width, $height, $color);
        imagecopy($canvas, $this->gd(), intdiv($width - $this->getWidth(), 2), intdiv($height - $this->getHeight(), 2), 0, 0, $this->getWidth(), $this->getHeight());
        $this->image = $canvas;

        return $this;
    }

    /**
     * Older name: fit inside width × height on a background of R, G, B.
     *
     * @param int $width
     * @param int $height
     * @param int $R
     * @param int $G
     * @param int $B
     * @return $this
     */
    public function smartResize($width, $height, $R, $G, $B)
    {
        return $this->pad((int) $width, (int) $height, [(int) $R, (int) $G, (int) $B]);
    }

    /**
     * Square-ish thumbnail: the longest side becomes $size.
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
     * @param int $width
     * @param int $height
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
     * @throws SystemException When width or height is below 1
     */
    public function crop($width, $height, $x, $y)
    {
        $width = (int) $width;
        $height = (int) $height;
        if ($width < 1 || $height < 1) {
            throw new SystemException(_('Width and height must be at least 1'));
        }

        $new = $this->canvas($width, $height, 'transparent');
        imagecopy($new, $this->gd(), 0, 0, max(0, (int) $x), max(0, (int) $y), $width, $height);
        $this->image = $new;

        return $this;
    }

    /**
     * Older name: copy width × height from x, y to x1, y1 on a new canvas.
     *
     * @return $this
     */
    public function smartCrop($width, $height, $x, $y, $x1, $y1)
    {
        $new = $this->canvas((int) $width, (int) $height, 'transparent');
        imagecopy($new, $this->gd(), (int) $x1, (int) $y1, (int) $x, (int) $y, $this->getWidth(), $this->getHeight());
        $this->image = $new;

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
        $transparent = imagecolorallocatealpha($this->gd(), 0, 0, 0, 127);
        $this->image = imagerotate($this->gd(), $degrees, $transparent);
        imagesavealpha($this->image, true);

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
        $mode = match ($direction) {
            'vertical', 'v' => IMG_FLIP_VERTICAL,
            'both' => IMG_FLIP_BOTH,
            default => IMG_FLIP_HORIZONTAL,
        };
        imageflip($this->gd(), $mode);

        return $this;
    }

    /**
     * Make the image black and white.
     *
     * @return $this
     */
    public function grayscale()
    {
        imagefilter($this->gd(), IMG_FILTER_GRAYSCALE);
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
        $this->image = $this->canvas((int) $width, (int) $height, [(int) $g, (int) $r, (int) $b]);
        return $this;
    }

    /**
     * Put a stamp (PNG) on the image.
     *
     * @param string|int  $y         'top', 'bottom' or pixels from the bottom
     * @param string|int  $x         'left', 'right' or pixels from the right
     * @param string|null $stampFile PNG in the project (default: config::get('stamp'))
     * @param int         $margin    Space from the edge in pixels
     * @return $this
     * @throws SystemException For an invalid position or a missing stamp
     */
    public function watermark($y, $x, ?string $stampFile = null, int $margin = 10)
    {
        $stampFile ??= (string) config::get('stamp', true);
        $stamp = $stampFile !== '' && is_file($stampFile) ? @imagecreatefrompng($stampFile) : false;
        if (!$stamp instanceof \GdImage) {
            throw new SystemException(sprintf(_('Could not read the watermark %s'), $stampFile));
        }

        $sx = imagesx($stamp);
        $sy = imagesy($stamp);

        $left = match (true) {
            $x === 'left' => $margin,
            $x === 'right' => $this->getWidth() - $sx - $margin,
            is_numeric($x) => $this->getWidth() - $sx - (int) $x,
            default => throw new SystemException(sprintf(_('Invalid x position for the watermark: %s'), $x)),
        };
        $top = match (true) {
            $y === 'top' => $margin,
            $y === 'bottom' => $this->getHeight() - $sy - $margin,
            is_numeric($y) => $this->getHeight() - $sy - (int) $y,
            default => throw new SystemException(sprintf(_('Invalid y position for the watermark: %s'), $y)),
        };

        imagealphablending($this->gd(), true);
        imagecopy($this->gd(), $stamp, $left, $top, 0, 0, $sx, $sy);

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
        $type = $image_type ?? (self::EXTENSIONS[strtolower(pathinfo($path, PATHINFO_EXTENSION))] ?? $this->image_type);

        if (!is_dir(dirname($path)) && !mkdir(dirname($path), 0775, true) && !is_dir(dirname($path))) {
            throw new SystemException(sprintf(_('Could not create the folder %s'), dirname($path)));
        }

        if (!$this->write($type, $path, (int) $compression)) {
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
        $this->write($image_type, null, (int) $compression);
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
        $this->write($image_type, null, (int) $compression);
        return 'data:' . image_type_to_mime_type($image_type) . ';base64,' . base64_encode((string) ob_get_clean());
    }

    /**
     * Write to a file, or to the output when $path is null.
     */
    private function write(int $type, ?string $path, int $compression): bool
    {
        $image = $this->gd();
        $write = $this->functions($type)[1];

        if ($type === IMAGETYPE_JPEG || $type === IMAGETYPE_BMP) {
            // No transparency in these: put the image on white
            $flat = $this->canvas($this->getWidth(), $this->getHeight(), '#ffffff');
            imagealphablending($flat, true);
            imagecopy($flat, $image, 0, 0, 0, 0, $this->getWidth(), $this->getHeight());
            $image = $flat;
        } else {
            imagealphablending($image, false);
            imagesavealpha($image, true);
        }

        $quality = max(0, min(100, $compression));

        return match ($type) {
            IMAGETYPE_JPEG, IMAGETYPE_WEBP => $write($image, $path, $quality),
            19 => $write($image, $path, $quality),
            IMAGETYPE_PNG => $write($image, $path, $compression >= 0 && $compression <= 9 ? $compression : 6),
            default => $write($image, $path),
        };
    }

    /**
     * Read and write functions for a type.
     *
     * @return array{0: string, 1: string}
     * @throws SystemException When this PHP cannot handle the type
     */
    private function functions(int $type): array
    {
        $functions = $type === 19 ? ['imagecreatefromavif', 'imageavif'] : (self::TYPES[$type] ?? null);

        if ($functions === null || !function_exists($functions[0]) || !function_exists($functions[1])) {
            throw new SystemException(sprintf(_('The image type is not supported: %s'), image_type_to_mime_type($type)));
        }

        return $functions;
    }

    /**
     * A new true colour canvas.
     *
     * @param int          $width
     * @param int          $height
     * @param string|array $color '#rrggbb', '#rgb', [r, g, b] or 'transparent'
     * @return \GdImage
     */
    private function canvas(int $width, int $height, string|array $color): \GdImage
    {
        $canvas = imagecreatetruecolor(max(1, $width), max(1, $height));
        imagealphablending($canvas, false);
        imagesavealpha($canvas, true);

        if ($color === 'transparent') {
            $fill = imagecolorallocatealpha($canvas, 0, 0, 0, 127);
        } else {
            [$r, $g, $b] = is_array($color) ? array_values($color) + [0, 0, 0] : self::hexColor($color);
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
    private static function hexColor(string $hex): array
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

    /**
     * Turn a JPEG the right way up from its EXIF orientation (photos from phones).
     */
    private function fixOrientation(string $filename): void
    {
        if (!function_exists('exif_read_data')) {
            return;
        }

        $orientation = (int) (@exif_read_data($filename)['Orientation'] ?? 1);
        match ($orientation) {
            2 => $this->flip('horizontal'),
            3 => $this->rotate(180),
            4 => $this->flip('vertical'),
            5 => $this->rotate(-90)->flip('horizontal'),
            6 => $this->rotate(-90),
            7 => $this->rotate(90)->flip('horizontal'),
            8 => $this->rotate(90),
            default => null,
        };
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
