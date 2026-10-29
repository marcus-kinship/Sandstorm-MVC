<?php


/**
 * FileInfo class for managing file properties and operations.
 *
 * This class provides methods for accessing and manipulating file properties,
 * such as permissions, timestamps, and content.
 *
 * @category _("File Handling class")
 */
class FileInfo
{

	/**
	 * Variables to store properties about the file.
	 * @var bool $isFile - Indicates whether the path is a file or not.
	 * @var string $pathName - Full path to the file.
	 * @var string $path - Directory where the file is located.
	 * @var string $baseName - Base name of the file.
	 * @var string $extension - File extension.
	 * @var string $fileName - File name without extension.
	 * @var int|false $size - Size of the file in bytes.
	 * @var string|false $perms - File permissions.
	 * @var int|false $CTime - File creation time.
	 * @var int|false $MTime - File modification time.
	 * @var int|false $ATime - File access time.
	 * @var bool $isWritable - Indicates whether the file is writable or not.
	 * @var bool $isReadble - Indicates whether the file is readable or not.
	 * @var bool $isExecutable - Indicates whether the file is executable or not.
	 * @var string $type - Type of the object, always "file" here.
	 */
	public $isFile = false;
	public $pathName  = "";
	public $path  = "";
	public $baseName  = "";
	public $extension  = "";
	public $fileName = "";
	public $size = false;
	public $perms = false;
	public $CTime = false;
	public $MTime = false;
	public $ATime = false;
	public $isWritable = false;
	public $isReadble = false;
	public $isExecutable = false;
	public $type = "";
	protected $pointer = "";
	protected $write = array();

	/**
	 * Constructor to create a new instance of FileInfo.
	 *
	 * @param string $path - The path to the file.
	 * @throws SystemException - Throws an exception if no path is provided or if the path does not point to a file.
	 */
	function __construct($path = "")
	{

		if ($path == "") {
			throw new SystemException(_("You must provide an address."));
		}

		if (is_file($path)) {
			$this->pathName = $path;
			$this->isFile = true;
			$this->setFileProperties();
		} else {
			throw new SystemException(_("It's not a file. %s"), $path);
		}
	}

	/**
	 * Magic method to convert the object to a string (useful for simple printing).
	 *
	 * @return string - Full path to the file.
	 */
	public function __toString()
	{
		return $this->pathName;
	}

	/**
	 * Private method to set properties for a file.
	 *
	 * @return void
	 */
	private function setFileProperties()
	{
		$p = $this->pathName;
		if (file_exists($p)) {

			$this->perms = substr(sprintf('%o', fileperms($p)), -4);
			$this->ATime = fileatime($p);
			$this->CTime = filectime($p);
			$this->MTime = filemtime($p);
			$this->isWritable = is_writable($p);
			$this->isReadble = is_readable($p);
			$this->size = filesize($p);
			$this->isExecutable = is_executable($p);
			$info = pathinfo($p);
			$this->path = $info['dirname'];
			$this->baseName = $info['basename'];
			$this->extension = $info['extension'];
			$this->fileName = $info['filename'];
			$this->type = "file";
		}
	}

	/**
	 * Sets the permissions for the file.
	 *
	 * @param string $permissions - The new permissions to set, in the form of four digits.
	 * @return void
	 * @throws SystemException - Throws an exception if the permissions format is incorrect or if they cannot be set.
	 */
	function setPerms($permissions)
	{
		if (preg_match('/\d{4}/', $permissions, $matches, PREG_OFFSET_CAPTURE, 0)) {
			if (!chmod($this->pathName, (int) $permissions)) {
				throw new SystemException(_("Failed to set the permissions as you specified %s"), $permissions);
			}
		} else {
			throw new SystemException(_("You specified wrong, You must specify four numbers. %s"), $permissions);
		}
	}

	/**
	 * Removes the file.
	 *
	 * @return bool - Returns true if the file is successfully removed, otherwise false.
	 * @throws SystemException - Throws an exception if the file cannot be removed.
	 */
	function remove()
	{
		if ($this->isFile) {
			if (!unlink($this->pathName)) {
				throw new SystemException(_("Failed to remove the file."));
			} else {
				return true;
			}
		} else {
			return false;
		}
	}

	/**
	 * Writes a string to the file.
	 *
	 * @param string $data - The string to write to the file.
	 * @return int|false - The number of bytes written, or false on failure.
	 */
	function writeaAString($data)
	{
		return file_put_contents($this->pathName, $data);
	}

	/**
	 * Reads a string from the file.
	 *
	 * @return string|false - The string read from the file, or false on failure.
	 */
	function readingAString()
	{
		return  file_get_contents($this->pathName);
	}

	/**
	 * Writes an array of data to the object's own attributes.
	 *
	 * @param array $data - The data to write.
	 * @return $this - Returns the object itself for possible method chaining.
	 */
	function write(array $data)
	{
		$this->write = $data;
		return $this;
	}

	/**
	 * Renames the file.
	 *
	 * @param string $name - The new name for the file.
	 * @return bool - Returns true if the renaming succeeds, otherwise false.
	 */
	function rename($name)
	{
		if ($this->isFile) {
			$name = $this->path . "/" . $name;
			if (rename($this->pathName, $name)) {
				return true;
			} else {
				return false;
			}
		} else {
			return false;
		}
		return $this;
	}

	/**
	 * Moves the file to a new path.
	 *
	 * @param string $path - The new path to which the file should be moved.
	 * @return bool - Returns true if the move succeeds, otherwise false.
	 */
	function move($path)
	{
		if ($this->isFile && rename($this->pathName, $path)) {
			return true;
		} else {
			return false;
		}
		return $this;
	}

	/**
	 * Reads the contents of the file line by line and returns it as an array of lines.
	 *
	 * @return array - An array containing the file's lines.
	 * @throws SystemException - Throws an exception if the file cannot be read.
	 */
	function reading()
	{
		$lines  = array();
		if ($this->isFile) {
			$path = $this->pathName;

			if (!$reading = fopen($path, 'r')) {
				throw new SystemException(_("Failed to read the file %s"), $path);
			}

			while (!feof($reading)) {
				$line = fgets($reading);
				$lines[] = $line;
			}
			fclose($reading);
		}
		return $lines;
	}

	/**
	 * Copies the file to a new destination.
	 *
	 * @param string $name - The new destination for the file.
	 * @return bool - Returns true if the copy succeeds, otherwise false.
	 * @throws SystemException - Throws an exception if the file cannot be copied.
	 */
	function copy($name)
	{
		if ($this->isFile) {
			$p = pathinfo($name, PATHINFO_DIRNAME);

			if ($p != ".") {
				$name = $this->path . "/" . $name;
			}
			if (!copy($this->pathName, $name)) {
				throw new SystemException(_("Failed to copy the file."));
			} else {
				return true;
			}
		} else {
			return false;
		}
	}

	/**
	 * Creates the file and fills it with content from the object's own attributes.
	 *
	 * @return bool - Returns true if the file is successfully created, otherwise false.
	 * @throws SystemException - Throws an exception if the file or the folder where it should be placed cannot be created.
	 */
	function insert()
	{
		$end = false;
		$path = $this->pathName;

		if (!file_exists($this->path)) {
			$this->makeFolder($this->path);
		}

		if (!file_exists($path)) {
			$myfile = fopen($path, "w");

			foreach ($this->write as $line) {
				fwrite($myfile, $line . "\n");
			}

			fclose($myfile);
			$end = true;
		}
		return $end;
	}

	/**
	 * Private method to create a folder structure based on the path.
	 *
	 * @param string $path - The path to the folder to be created.
	 * @param int $r - Permissions for the created folder.
	 * @return bool - Returns true if the folder is successfully created, otherwise false.
	 * @throws SystemException - Throws an exception if there are problems creating the folder.
	 */
	private function makeFolder($path, $r = 0777)
	{

		$path = explode("/", $path);
		$structure = '';

		foreach ($path as $dir) {
			$structure .= "/" . trim($dir);
			if (!is_dir($structure) && strlen($structure > 0) && !mkdir($structure, $r, true)) {
				throw new SystemException(_("Failed to create the folder %s"), $structure);
			}
		}

		return true;
	}

	/**
	 * Private method to insert content before or after a given position in the file.
	 *
	 * @param string $where - The position where the content should be inserted.
	 * @return $this - Returns the object itself for possible method chaining.
	 * @throws SystemException - Throws an exception if it cannot read, write, or rename the file.
	 */
	private function pinsert($where)
	{

		$path = $this->pathName;

		if (!$reading = fopen($path, 'r')) {
			throw new SystemException(_("Failed to read the file %s"), $path);
		}

		if (!$writing = fopen($path . '.tmp', 'w')) {
			throw new SystemException(_("Failed to write to disk"));
		}

		$replaced = false;

		if ($this->pointer == "before") {
			$tmp = "";
			while (!feof($reading)) {
				$line = fgets($reading);

				if (stristr($line, $where)) {

					foreach ($this->write as $v) {
						$tmp .= $v . "\n" . $line;
					}

					$replaced = true;
				}

				fputs($writing, $line);
			}
		}

		if ($this->pointer == "after") {

			while (!feof($reading)) {
				$line = fgets($reading);

				if (stristr($line, $where)) {

					foreach ($this->write as $v) {
						$line .= "\n" . $v;
					}
					$replaced = true;
				}
				fputs($writing, $line);
			}
		}

		fclose($reading);
		fclose($writing);

		if ($replaced) {
			rename($path . '.tmp', $path);
		} else {
			unlink($path . '.tmp');
		}

		return $this;
	}

	/**
	 * Inserts content before a given position in the file.
	 *
	 * @param string $where - The position where the content should be inserted.
	 * @return $this - Returns the object itself for possible method chaining.
	 */
	function insertBefore($where)
	{

		$this->pointer = "before";
		$this->insert($where);
		return $this;
	}

	/**
	 * Inserts content after a given position in the file.
	 *
	 * @param string $where - The position where the content should be inserted.
	 * @return $this - Returns the object itself for possible method chaining.
	 */
	function insertAfter($where)
	{

		$this->pointer = "after";
		$this->insert($where);
		return $this;
	}
}
