<?

/**
 * Represents information and operations for folders.
 * 
 *  * @category Utility class
 */
class FolderInfoIO
{

	/** @var bool Indicates if the path represents a directory. */
	public $isDir = false;

	/** @var string The full path to the folder. */
	public $pathName  = "";

	/** @var string The directory portion of the path. */
	public $path  = "";

	/** @var string The base name of the folder. */
	public $baseName  = "";

	/** @var string The name of the folder without the path. */
	public $fileName = "";

	/** @var string|bool Permissions of the folder. */
	public $perms = false;

	/** @var int|bool Creation time of the folder. */
	public $CTime = false;

	/** @var int|bool Modification time of the folder. */
	public $MTime = false;

	/** @var int|bool Last access time of the folder. */
	public $ATime = false;

	/** @var bool Indicates if the folder is writable. */
	public $isWritable = false;

	/** @var bool Indicates if the folder is readable. */
	public $isReadable = false;

	/** @var string Type of the folder. */
	public $type = "";

	/** @var mixed A pointer to the folder. */
	protected $pointer = "";

	/**
	 * Constructor to initialize the class with a folder path.
	 *
	 * @param string $path The path to the folder.
	 * @throws SystemException If the path is empty or not a directory.
	 */
	function __construct($path = "")
	{
		if ($path == "") {
			throw new SystemException(_("Du måste ange en adress."));
		}
		if (is_dir($path)) {
			$this->pathName = $path;
			$this->isDir = true;
			$this->setFilProperties();
		} else {
			throw new SystemException(sprintf(_("Det är inte någon mapp. %s"), $path));
		}
	}

	/**
	 * Magic method to convert the object to a string.
	 *
	 * @return string The path to the folder.
	 */
	public function __toString()
	{
		return $this->pathName;
	}

	/**
	 * Sets file properties such as permissions and timestamps.
	 *
	 * @throws SystemException If unable to retrieve file properties.
	 */
	private function setFilProperties()
	{
		$p = $this->pathName;
		if (file_exists($p)) {
			$this->perms = substr(sprintf('%o', fileperms($p)), -4);
			$this->ATime = fileatime($p);
			$this->CTime = filectime($p);
			$this->MTime = filemtime($p);
			$this->isWritable = is_writable($p);
			$this->isReadable = is_readable($p);
			$info = pathinfo($p);
			$this->path = $info['dirname'];
			$this->baseName = $info['basename'];
			$this->fileName = $info['filename'];
			$this->type = "dir";
		}
	}

	/**
	 * Sets permissions for the folder.
	 *
	 * @param string $permissions The permissions to set.
	 * @throws SystemException If unable to set permissions.
	 */
	function setPerms($permissions)
	{
		if (preg_match('/\d{4}/', $permissions, $matches, PREG_OFFSET_CAPTURE, 0)) {
			if (!chmod($this->pathName, (int) $permissions)) {
				throw new SystemException(sprintf(_("Det gick inte sätt behörigheterna som du angett du %s"), $permissions));
			}
		} else {
			throw new SystemException(sprintf(_("Du ange fel,Du måste ange fyra nummer. %s"), $permissions));
		}
	}

	/**
	 * Removes the folder.
	 *
	 * @return bool True if the folder is successfully removed, otherwise false
	 * @throws SystemException If unable to remove the folder
	 */
	function remove()
	{
		if ($this->isDir) {
			if (!rmdir($this->pathName)) {
				throw new SystemException(_("Det gick inte ta bort mapp filen."));
			} else {
				return true;
			}
		} else {
			return false;
		}
	}

	/**
	 * Creates a new folder inside the current folder.
	 *
	 * @param string $name Name of the new folder
	 * @param int $r Permissions for the new folder (default is 0777)
	 * @return bool True if the folder is successfully created, otherwise false
	 */
	function newFolder($name, $r = 0777)
	{
		if ($this->isDir) {
			$name = $this->path . DIRECTORY_SEPARATOR . $name;
			if (mkdir($name, $r)) {
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
	 * Renames the folder.
	 *
	 * @param string $name New name for the folder
	 * @return bool True if the folder is successfully renamed, otherwise false
	 */
	function rename($name)
	{
		if ($this->isDir) {
			$name = $this->path . DIRECTORY_SEPARATOR . $name;
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
	 * Retrieves the tree structure of the folder.
	 *
	 * @return array An array containing the tree structure of the folder
	 */
	function getTree()
	{
		$files = glob($this->pathName, GLOB_BRACE);
		foreach ($files as $k => $dir) {
			$files = array_merge($files, $this->getList($dir . DIRECTORY_SEPARATOR . '*'));
		}
		return $files;
	}

	/**
	 * Retrieves the list of files and subfolders inside the folder.
	 *
	 * @return array An array containing the list of files and subfolders
	 */
	function getList()
	{
		$files = glob($this->pathName . DIRECTORY_SEPARATOR . '*', GLOB_BRACE);
		foreach ($files as $k => $dir) {
			if (is_file($dir)) {
				$files[] = new FileInfoIO($dir);
			}

			if (is_dir($dir)) {
				$files[] = new FolderInfoIO($dir);
			}
		}
		return $files;
	}

	/**
	 * Searches for files and subfolders matching the given regular expression.
	 *
	 * @param string $regex Regular expression to search for
	 * @param bool $recursive Whether to search recursively in subfolders (default is false)
	 * @return array An array containing the list of matching files and subfolders
	 */
	function search(string $regex, $recursive = false)
	{
		$files = array();

		if ($this->isDir) {
			$flags = 0;
			if ($recursive) {
				$dir = new RecursiveDirectoryIterator($this->pathName);
				$ite = new RecursiveIteratorIterator($dir);
				$filesIterator = new RegexIterator($ite, '/.*' . $regex . '/i', RegexIterator::GET_MATCH);

				foreach ($filesIterator as $file) {
					if (is_file($file[0])) {
						$files[] = new FileInfoIO($file[0]);
					}
				}
			} else {
				$files = glob($this->pathName . DIRECTORY_SEPARATOR . '@.*' . $regex . '@i', GLOB_BRACE);
				foreach ($files as $k => $dir) {
					if (is_file($dir)) {
						$files[] = new FileInfoIO($dir);
					}

					if (is_dir($dir)) {
						$files[] = new FolderInfoIO($dir);
					}
				}
			}
		}

		return $files;
	}

	/**
	 * Moves the folder and its contents to the specified path.
	 *
	 * @param string $path The destination path where the folder and its contents will be moved
	 * @return bool True if the folder and its contents are successfully moved, otherwise false
	 */
	function move($path)
	{
		if ($this->isDir) {
			$files = $this->getTree();

			foreach ($files as $filename) {
				$p = pathinfo($filename);
				$d = str_replace($this->path, "", $filename);
				$target = $path . DIRECTORY_SEPARATOR . $d;
				if (!file_exists($target) && is_dir($target)) {
					$this->makeFolder($target);
				}
				if (is_file($target) && !rename($filename, $path . DIRECTORY_SEPARATOR . $d)) {
					return false;
				}
			}
		} else {
			return false;
		}
	}

	/**
	 * Copies the folder and its contents to the specified path.
	 *
	 * @param string $path The destination path where the folder and its contents will be copied
	 * @return bool True if the folder and its contents are successfully copied, otherwise false
	 */
	function copy($path)
	{
		if ($this->isDir) {
			$files = $this->getTree();

			foreach ($files as $filename) {
				$p = pathinfo($filename);
				$d = str_replace($this->path, "", $filename);
				$target = $path . DIRECTORY_SEPARATOR . $d;
				if (!file_exists($target) && is_dir($target)) {
					$this->makeFolder($target);
				}
				if (is_file($target) && !copy($filename, $path . DIRECTORY_SEPARATOR . $d)) {
					return false;
				}
			}
		} else {
			return false;
		}
	}
}