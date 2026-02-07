<?php

namespace Sys\Request;

/**
 * Validator class for request data
 * 
 * Provides chainable validation and transformation methods for request data.
 * Supports type conversion, sanitization, and cleaning.
 *
 * @category _("Request Validation")
 * @package  Sys\Request
 */
class Validator
{
    /**
     * @var mixed The value being validated
     */
    private $value;

    /**
     * @var string The source type (get, post, request)
     */
    private $source;

    /**
     * @var string The key name
     */
    private $key;

    /**
     * @var bool Whether validation has failed
     */
    private $failed = false;

    /**
     * Constructor
     *
     * @param mixed  $value  The value to validate
     * @param string $source The source type (get, post, request)
     * @param string $key    The key name
     */
    public function __construct($value, string $source, string $key)
    {
        $this->value = $value;
        $this->source = $source;
        $this->key = $key;
    }

    /**
     * Convert value to integer
     *
     * @param int|null $default Default value if conversion fails
     * @return self|int Returns self for chaining or final integer value
     */
    public function int($default = null)
    {
        if ($this->failed) {
            return $default ?? 0;
        }

        if ($this->value === null || $this->value === '') {
            $this->failed = true;
            return $default ?? 0;
        }

        $result = filter_var($this->value, FILTER_VALIDATE_INT);
        
        if ($result === false) {
            $this->failed = true;
            return $default ?? 0;
        }

        return $result;
    }

    /**
     * Convert value to float
     *
     * @param float|null $default Default value if conversion fails
     * @return self|float Returns self for chaining or final float value
     */
    public function float($default = null)
    {
        if ($this->failed) {
            return $default ?? 0.0;
        }

        if ($this->value === null || $this->value === '') {
            $this->failed = true;
            return $default ?? 0.0;
        }

        $result = filter_var($this->value, FILTER_VALIDATE_FLOAT);
        
        if ($result === false) {
            $this->failed = true;
            return $default ?? 0.0;
        }

        return $result;
    }

    /**
     * Convert value to string and apply basic sanitization
     *
     * @param string|null $default Default value if conversion fails
     * @return self Returns self for chaining
     */
    public function string($default = null)
    {
        if ($this->failed) {
            $this->value = $default ?? '';
            return $this;
        }

        if ($this->value === null) {
            $this->value = $default ?? '';
            $this->failed = true;
            return $this;
        }

        $this->value = (string) $this->value;
        return $this;
    }

    /**
     * Trim whitespace from string
     *
     * @return self Returns self for chaining
     */
    public function trim()
    {
        if (!$this->failed && is_string($this->value)) {
            $this->value = trim($this->value);
        }
        return $this;
    }

    /**
     * Escape HTML entities
     *
     * @return self|string Returns self for chaining or final escaped string
     */
    public function escape()
    {
        if ($this->failed) {
            return $this->value;
        }

        if (is_string($this->value)) {
            return htmlentities($this->value, ENT_QUOTES, 'UTF-8');
        }

        return $this->value;
    }

    /**
     * Prepare value for HTML output (alias for escape)
     *
     * @return self Returns self for chaining
     */
    public function html()
    {
        return $this;
    }

    /**
     * Clean HTML content using HTMLPurifier or strip_tags
     *
     * @param array $allowedTags Optional array of allowed HTML tags
     * @return string Returns cleaned HTML string
     */
    public function clean(array $allowedTags = [])
    {
        if ($this->failed || !is_string($this->value)) {
            return $this->value ?? '';
        }

        // strip_tags
        if (!empty($allowedTags)) {
            return strip_tags($this->value, '<' . implode('><', $allowedTags) . '>');
        }

        return strip_tags($this->value);
    }

    /**
     * Validate email address
     *
     * @param string|null $default Default value if validation fails
     * @return string|false Returns validated email or false/default
     */
    public function email($default = null)
    {
        if ($this->failed) {
            return $default ?? false;
        }

        $result = filter_var($this->value, FILTER_VALIDATE_EMAIL);
        
        if ($result === false) {
            $this->failed = true;
            return $default ?? false;
        }

        return $result;
    }

    /**
     * Validate URL
     *
     * @param string|null $default Default value if validation fails
     * @return string|false Returns validated URL or false/default
     */
    public function url($default = null)
    {
        if ($this->failed) {
            return $default ?? false;
        }

        $result = filter_var($this->value, FILTER_VALIDATE_URL);
        
        if ($result === false) {
            $this->failed = true;
            return $default ?? false;
        }

        return $result;
    }

    /**
     * Check if value matches a regular expression pattern
     *
     * @param string $pattern Regex pattern to match
     * @param mixed  $default Default value if validation fails
     * @return mixed Returns value if match, otherwise default
     */
    public function regex(string $pattern, $default = null)
    {
        if ($this->failed) {
            return $default ?? false;
        }

        if (preg_match($pattern, $this->value)) {
            return $this->value;
        }

        $this->failed = true;
        return $default ?? false;
    }

    /**
     * Set minimum length for string
     *
     * @param int  $min     Minimum length
     * @param mixed $default Default value if validation fails
     * @return self Returns self for chaining
     */
    public function minLength(int $min, $default = null)
    {
        if (!$this->failed && is_string($this->value) && strlen($this->value) < $min) {
            $this->failed = true;
            $this->value = $default ?? '';
        }
        return $this;
    }

    /**
     * Set maximum length for string
     *
     * @param int  $max     Maximum length
     * @param bool $truncate Whether to truncate or fail
     * @return self Returns self for chaining
     */
    public function maxLength(int $max, bool $truncate = false)
    {
        if (!$this->failed && is_string($this->value) && strlen($this->value) > $max) {
            if ($truncate) {
                $this->value = substr($this->value, 0, $max);
            } else {
                $this->failed = true;
                $this->value = '';
            }
        }
        return $this;
    }

    /**
     * Get the current value
     *
     * @return mixed The current value
     */
    public function getValue()
    {
        return $this->value;
    }

    /**
     * Check if validation has failed
     *
     * @return bool True if validation failed
     */
    public function hasFailed()
    {
        return $this->failed;
    }

    /**
     * Magic method to return value when object is used as string
     *
     * @return string String representation of value
     */
    public function __toString()
    {
        return (string) $this->value;
    }
}
