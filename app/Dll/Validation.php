<?php

use Dll\Validation\Password;


/**
 * Validation class for form validation.
 * 
 * @category _("Dynamiskt länkbibliotek klass")
 */
class Validation
{

	private $data = array();
	private $inputs = array();
	private $errors = array();
	private $curname;
	public $formisvalid = false;
	private $check = true;
	private static $atoken = "";
	private $names = array();

	/**
	 * Constructor
	 */
	public function __construct()
	{
	}

	/**
	 * Set input value
	 *
	 * @param string $name
	 * @param bool $strip
	 * @return void
	 */
	private function setInput($name, $strip)
	{
		if ($strip) {
			$this->inputs[$name] = strip_tags(Request::req($name));
		} else {
			$this->inputs[$name] = Request::req($name);
		}
	}

	/**
	 * Prevent caching
	 *
	 * @return void
	 */
	static function noCache()
	{
		header("Cache-Control: no-cache, no-store, must-revalidate"); // HTTP 1.1.
		header("Pragma: no-cache"); // HTTP 1.0.
		header("Expires: 0");
	}

	/**
	 * Generate or retrieve token
	 *
	 * @param string $key
	 * @param bool $html
	 * @return string
	 */
	static function token($key, $html = false)
	{
		if (Session::get('token_' . $key)) {
			$token = Session::get('token_' . $key);
		} else {
			$token = md5(uniqid(mt_rand(), true));
			Session::set('token_' . $key, $token);
		}

		if ($html == true) {
			$token = '<input type="hidden" name="token" value="' . $token . '">';
		}
		return $token;
	}

	/**
	 * Get token value
	 *
	 * @param string $key
	 * @return string|false
	 */
	function getToken($key)
	{
		$token = session::get('token_' . $key);
		self::$atoken = $key;
		if (Request::req('token') && Request::req('token') == $token) {
		} else {
			$token = false;
		}
		return $token;
	}

	/**
	 * Get input value
	 *
	 * @param bool $strip
	 * @return mixed
	 */
	function value($strip = true)
	{
		if ($strip) {
			return strip_tags(Request::req($this->curname));
		} else {
			return Request::req($this->curname);
		}
	}

	/**
	 * Set input name
	 *
	 * @param string|null $name
	 * @param bool $strip
	 * @return $this
	 */
	function set($name = null, $strip = true)
	{
		if (!$name == null) {
			$this->curname = $name;
			$this->names[$name] = $strip;
			return $this;
		} else {
			throw new SystemException(sprintf(_("Du måste ange ett namn först innan du kan anropa en regeln")));
		}
	}

	/**
	 * Get input value
	 *
	 * @param string $name
	 * @return mixed|null
	 */
	function get($name)
	{
		if (array_key_exists($name, $this->inputs)) {
			return $this->inputs[$name];
		}
		return null;
	}

	/**
	 * Set validation rule
	 *
	 * @param string $msg
	 * @param array $rule
	 * @return $this
	 */
	function rule($msg, array $rule)
	{
		if (func_num_args() > 1) {
			$this->data[$this->curname][$msg] = $rule;
			return $this;
		} else {
			throw new SystemException(sprintf(_("Du måste ange ett meddelande först innan du kan ställa in regel")));
		}
		return $this;
	}

	/**
	 * Set strip rule
	 *
	 * @param bool $strip
	 * @return $this
	 */
	function strip($strip = false)
	{
		$this->names[$this->curname] = $strip;
		return $this;
	}

	/**
	 * Set custom rule
	 *
	 * @param string $msg
	 * @param Closure $function
	 * @return $this
	 */
	function cRule($msg, Closure $function)
	{
		if (func_num_args() > 1) {
			$this->data[$this->curname][$msg] = $function;
			return $this;
		} else {
			throw new SystemException(sprintf(_("Du måste ange ett meddelande först innan du kan ställa in regel")));
		}
		return $this;
	}

	/**
	 * Validate form inputs
	 *
	 * @return bool
	 */
	private function engine()
	{

		if (Request::isPost() == true) {
			$this->formisvalid = true;

			foreach ($this->names as $k => $v) {
				$this->setInput($k, $v);
			}
			$i = 0;

			foreach ($this->data as $name => $msg) {

				foreach ($msg as $text => $function) {
					if ($this->check) {

						if (is_callable($function)) {

							$this->check = $function($name, $this);
							if ($this->check != true) {
								$this->errors[$i]['name'] = $name;
								$this->errors[$i]['message'] = $text;
								$i++;
							}
						} else {

							foreach ($function as $fname => $args) {

								if ($function[$fname] == "") {

									$this->check = $this->$fname($this->inputs[$name]);
									if ($this->check != true) {

										$this->errors[$i]['name'] = $name;
										$this->errors[$i]['message'] = $text;
										$i++;
									}
								} else {

									if ($this->data[$name][$text][$fname] !== "") {

										$this->check = $this->$fname($this->inputs[$name], $function[$fname], $this->data[$name][$text][$fname]);
									} else {
										$this->check = $this->$fname($this->inputs[$name], $function[$fname]);
									}

									if ($this->check != true) {

										$this->errors[$i]['name'] = $name;
										$this->errors[$i]['message'] = $text;
										$i++;
									}
								}
							}
						}
					} else {

						return $this->check;
					}
				}
			}

			return $this->check;
		}
	}

	/**
	 * Check form validation
	 *
	 * @return bool
	 */
	function valid()
	{
		if ($this->formisvalid != true) {
			return $this->engine();
		}
		if ($this->check) {
			Session::remove(self::$atoken);
		}
		return $this->check;
	}

	/**
	 * Execute function before validation
	 *
	 * @param Closure $function
	 * @return void
	 */
	function beforeValid(Closure $function)
	{
		$this->inputs = $function($this->inputs);
	}

	/**
	 * Execute function on successful validation
	 *
	 * @param Closure $function
	 * @return mixed
	 */
	function success(Closure $function)
	{
		if ($this->valid()) {
			return $function($this);
		}
	}

	/**
	 * Get first validation error
	 *
	 * @return mixed|null
	 */
	function getFirstError()
	{

		if (array_key_exists(0, $this->errors)) {
			return $this->errors[0];
		}
		return null;
	}

	/**
	 * Print debugging information
	 *
	 * @return string
	 */
	function debug()
	{
		return print_r($this);
	}

	/**
	 * Check if value is not empty
	 *
	 * @param mixed $value
	 * @return bool
	 */
	function blank($value)
	{
		if ($value != "") {
			return true;
		} else {
			return false;
		}
	}

	/**
	 * Check if value is a valid email
	 *
	 * @param string $value
	 * @return bool
	 */
	function email($value)
	{
		if (filter_var($value, FILTER_VALIDATE_EMAIL)) {
			return true;
		} else {
			return false;
		}
	}

	/**
	 * Check if value is numeric
	 *
	 * @param mixed $value
	 * @return bool
	 */
	function numeric($value)
	{
		return is_numeric($value);
	}

	/**
	 * Check if value contains only string characters
	 *
	 * @param string $value
	 * @return bool
	 */
	function string($value)
	{
		if (!preg_match('/[^0-9A-Öa-ö_:\?\.-]/i', $value)) {
			return true;
		} else {
			return false;
		}
	}

	/**
	 * Check if two values match
	 *
	 * @param mixed $a
	 * @param mixed $b
	 * @return bool
	 */
	function match($a, $b)
	{
		if ($a === $b) {
			return true;
		} else {
			return false;
		}
	}

	/**
	 * Check if value length is greater than or equal to a minimum value
	 *
	 * @param string $value
	 * @param int $min
	 * @return bool
	 */
	function minlength($value, $min)
	{

		if (strlen($value) >= intval($min)) {

			return true;
		} else {
			return false;
		}
	}

	/**
	 * Get random string
	 *
	 * @param int $length
	 * @return string
	 */
	static function getrandomstring($length = 8)
	{
		return base64_encode(openssl_random_pseudo_bytes($length));
	}

	/**
	 * Check if value length is less than or equal to a maximum value
	 *
	 * @param string $value
	 * @param int $max
	 * @return bool
	 */
	function maxlength($value, $max)
	{
		if (strlen($value) <= intval($max)) {
			return true;
		} else {
			return false;
		}
	}

	/**
	 * Check that a new password is good enough (see Password::check()).
	 *
	 * <code>
	 * $form->set('password')->rule(_('Choose a stronger password'), ['password' => 8]);
	 * </code>
	 *
	 * @param string $value
	 * @param int    $minLength Shortest allowed password (default 8)
	 * @return bool
	 */
	function password($value, $minLength = Password::MIN_LENGTH)
	{
		return Password::isStrong((string) $value, (int) ($minLength ?: Password::MIN_LENGTH));
	}
}
