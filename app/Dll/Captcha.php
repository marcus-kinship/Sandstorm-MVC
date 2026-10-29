<?php

/**
 * Older name for Dll\Validation\Captcha; kept so existing code keeps working.
 *
 * As before, new Captcha(...) sends the image at once, and Captcha::vaild()
 * checks the code. New code: (new \Dll\Validation\Captcha())->image() and
 * \Dll\Validation\Captcha::check().
 *
 * @category _("Captcha class")
 */
class Captcha extends \Dll\Validation\Captcha
{
    /**
     * Send a captcha image (the older positional settings still work).
     */
    public function __construct($length = null, $image_type = null, $font = null, $char_width = null, $text_color = null,
        $background_color = null, $cases = null, $image_height = null, $image_padding = null)
    {
        parent::__construct(compact('length', 'image_type', 'font', 'char_width', 'text_color', 'background_color', 'image_height', 'image_padding'));
        $this->image();
    }

    /**
     * Older name (and spelling) for check().
     *
     * @param mixed $str
     * @return bool
     */
    public static function vaild($str)
    {
        return self::check($str);
    }
}
