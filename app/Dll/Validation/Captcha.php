<?php

namespace Dll\Validation;

/**
 * Captcha: an image with a code that a person types back.
 *
 * <code>
 * // In the controller that serves the image (for example /captcha):
 * (new \Dll\Validation\Captcha())->image();           // sends the PNG and remembers the code
 *
 * // When the form is posted:
 * if (\Dll\Validation\Captcha::check($_POST['captcha'] ?? '')) { ... }
 *
 * // Or as a Validation rule:
 * $form->set('captcha')->rule(_('Wrong code'), ['captcha' => '']);
 * </code>
 *
 * Safe on purpose:
 * - the code comes from random_int() (not mt_rand(), which can be predicted)
 * - only a hash of it is kept in the session, and it works once: a check,
 *   right or wrong, uses it up, so the same code cannot be tried many times
 * - it expires after 10 minutes (setting captcha.lifetime)
 * - it is compared in constant time, and capital letters do not matter
 *
 * Settings in app/config/settings/captcha.ini.php (all optional): length (5),
 * image_type (png, jpeg or gif), font (a .ttf file; without one GD's own font
 * is used), char_width (20), text_color (333333), background_color (ffffff),
 * image_height (40), image_padding (10), lifetime (600 seconds).
 *
 * @category _("Captcha class")
 */
class Captcha
{
    /** Session key of the current code */
    private const SESSION = 'captcha';

    /** Letters and digits that cannot be mistaken for each other (no 0/O, 1/I/L) */
    private const CHARS = 'ABCDEFGHJKMNPQRSTUVWXYZ23456789';

    /** @var array<string, mixed> */
    protected array $options;

    /**
     * @param array $options Same keys as the settings (see the class comment); they win over the settings
     */
    public function __construct(array $options = [])
    {
        $defaults = [
            'length' => 5, 'image_type' => 'png', 'font' => '', 'char_width' => 20, 'text_color' => '333333',
            'background_color' => 'ffffff', 'image_height' => 40, 'image_padding' => 10, 'lifetime' => 600,
        ];

        $settings = \config::get('captcha.*', true);
        $this->options = array_filter($options, fn($v) => $v !== null && $v !== '') + (is_array($settings) ? $settings : []) + $defaults;
    }

    /**
     * Make a new code and remember it in the session (as a hash).
     *
     * @return string The code (to draw it yourself; image() does it for you)
     */
    public function generate(): string
    {
        $code = '';
        $length = max(3, min(10, (int) $this->options['length']));
        for ($i = 0; $i < $length; $i++) {
            $code .= self::CHARS[random_int(0, strlen(self::CHARS) - 1)];
        }

        \Session::set(self::SESSION, [
            'hash' => hash('sha256', $code),
            'expires' => time() + max(30, (int) $this->options['lifetime']),
        ]);

        return $code;
    }

    /**
     * Make a new code and send it as an image (with no-cache headers).
     *
     * @return void
     * @throws \SystemException When GD is not installed
     */
    public function image(): void
    {
        $code = $this->generate();
        $type = in_array($this->options['image_type'], ['png', 'jpeg', 'gif'], true) ? $this->options['image_type'] : 'png';

        if (!headers_sent()) {
            header('Content-Type: image/' . $type);
            header('Cache-Control: no-store, no-cache, must-revalidate');
        }

        $image = $this->draw($code);
        match ($type) {
            'jpeg' => imagejpeg($image),
            'gif' => imagegif($image),
            default => imagepng($image),
        };
    }

    /**
     * Whether the typed code is right. The code is used up either way.
     *
     * @param mixed $input What the visitor typed
     * @return bool False when wrong, expired or no code was made
     */
    public static function check($input): bool
    {
        if (!\Session::exists()) {
            return false;
        }

        $stored = \Session::get(self::SESSION, null);
        \Session::remove(self::SESSION);

        if (!is_array($stored) || ($stored['expires'] ?? 0) < time() || !is_string($input)) {
            return false;
        }

        return hash_equals((string) ($stored['hash'] ?? ''), hash('sha256', strtoupper(trim($input))));
    }

    /**
     * Draw the code with some noise.
     *
     * @param string $code
     * @return \GdImage
     * @throws \SystemException When GD is not installed
     */
    protected function draw(string $code): \GdImage
    {
        if (!function_exists('imagecreatetruecolor')) {
            throw new \SystemException(_('The captcha needs the GD extension'));
        }

        $charWidth = max(10, (int) $this->options['char_width']);
        $padding = max(0, (int) $this->options['image_padding']);
        $width = strlen($code) * $charWidth + 2 * $padding;
        $height = max(20, (int) $this->options['image_height']);

        $image = imagecreatetruecolor($width, $height);
        $background = self::color($image, (string) $this->options['background_color']);
        $text = self::color($image, (string) $this->options['text_color']);
        imagefilledrectangle($image, 0, 0, $width, $height, $background);

        $font = (string) $this->options['font'];
        $ttf = $font !== '' && is_file($font) && function_exists('imagettftext');

        for ($i = 0, $x = $padding; $i < strlen($code); $i++, $x += $charWidth) {
            if ($ttf) {
                imagettftext($image, $charWidth * 0.8, random_int(-15, 15), $x, (int) ($height * 0.75), $text, $font, $code[$i]);
            } else {
                imagestring($image, 5, $x + intdiv($charWidth - imagefontwidth(5), 2), random_int(2, max(2, $height - imagefontheight(5) - 2)), $code[$i], $text);
            }
        }

        // Noise: lines and dots in the text colour
        for ($i = 0; $i < 4; $i++) {
            imageline($image, random_int(0, $width), random_int(0, $height), random_int(0, $width), random_int(0, $height), $text);
        }
        for ($i = 0; $i < $width * $height / 12; $i++) {
            imagesetpixel($image, random_int(0, $width - 1), random_int(0, $height - 1), $text);
        }

        return $image;
    }

    /**
     * Allocate 'rrggbb' (with or without #) on the image.
     */
    private static function color(\GdImage $image, string $hex): int
    {
        $hex = ltrim($hex, '#');
        if (!preg_match('/^[0-9a-f]{6}$/i', $hex)) {
            $hex = '000000';
        }

        return imagecolorallocate($image, hexdec(substr($hex, 0, 2)), hexdec(substr($hex, 2, 2)), hexdec(substr($hex, 4, 2)));
    }
}
