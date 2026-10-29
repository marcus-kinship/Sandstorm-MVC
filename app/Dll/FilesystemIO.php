<?
class FilesystemIO
{
	protected $source = "";
	protected $one = false;

	function __construct($sources)
	{

		$this->source = $_SERVER['DOCUMENT_ROOT'] . "/" . ltrim($sources, '/');
	}

	function getSource()
	{
		return $this->source;
	}

	static function path($sources)
	{
		$fs = new Filesystem($sources);
		return $fs;
	}

	function getDir($type = '*')
	{
		$tmp = "";
		if (is_dir($this->source)) {

			$tmp = array_merge(glob($this->source . $type));
		}

		return $tmp;
	}

	function getInfo()
	{
		if (is_file($this->source)) {
			return	pathinfo($this->source);
		}
	}

	function rename($name)
	{
		if (is_file($this->source)) {

			return rename($this->source, $_SERVER['DOCUMENT_ROOT'] . "/" . ltrim($name, '/'));
		}
	}

	function copy($name)
	{
		if (is_file($this->source)) {

			return copy($this->source, $_SERVER['DOCUMENT_ROOT'] . "/" . ltrim($name, '/'));
		}
	}

	function remove()
	{
		array_map("unlink", glob($this->source));
	}
}
