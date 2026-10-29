<?php

/**
 * A file in the project: its properties, content and file operations.
 *
 * Paths are locked to the project folder (see Filesystem::resolve()).
 *
 * <code>
 * $log = new FileInfo('app/log/today.txt', false);   // may not exist yet
 * $log->put("first line\n");                          // creates folders and file
 * $log->put("more\n", true);                           // append
 * echo $log->read();
 *
 * $conf = new FileInfo('app/config/settings/site.ini.php');
 * $conf->write(['    // added'])->insertAfter('$this->set = array(');
 * $conf->copy('app/config/settings/site.backup.php');
 * </code>
 *
 * @category _("File Handling class")
 */
class FileInfo
{
    /** @var bool True when the file exists */
    public $isFile = false;

    /** @var string Full path */
    public $pathName = "";

    /** @var string Folder of the file */
    public $path = "";

    /** @var string Name with extension */
    public $baseName = "";

    /** @var string Extension without dot ('' when none) */
    public $extension = "";

    /** @var string Name without extension */
    public $fileName = "";

    /** @var int|false Size in bytes */
    public $size = false;

    /** @var string|false Permissions, for example "0644" */
    public $perms = false;

    /** @var int|false Change time */
    public $CTime = false;

    /** @var int|false Modification time */
    public $MTime = false;

    /** @var int|false Access time */
    public $ATime = false;

    /** @var bool */
    public $isWritable = false;

    /** @var bool */
    public $isReadable = false;

    /** @var bool Same as $isReadable (older, misspelled name) */
    public $isReadble = false;

    /** @var bool */
    public $isExecutable = false;

    /** @var string Always "file" */
    public $type = "file";

    /** @var string[] Lines for insert(), insertBefore() and insertAfter() */
    protected $write = [];

    /**
     * @param string $path      Path from the project folder, or absolute inside it
     * @param bool   $mustExist False to work with a file that does not exist yet
     * @throws \RuntimeException When the path is outside the project
     * @throws SystemException When the file must exist and does not, or the path is a folder
     */
    public function __construct($path = "", bool $mustExist = true)
    {
        $this->pathName = Filesystem::resolve((string) $path);

        if (is_dir($this->pathName)) {
            throw new SystemException(sprintf(_("It's a folder, not a file: %s"), $path));
        }
        if ($mustExist && !is_file($this->pathName)) {
            throw new SystemException(sprintf(_("It's not a file: %s"), $path));
        }

        $this->refresh();
    }

    /**
     * The full path.
     *
     * @return string
     */
    public function __toString()
    {
        return $this->pathName;
    }

    /**
     * Read the properties again (after a change on disk).
     *
     * @return $this
     */
    public function refresh()
    {
        clearstatcache(true, $this->pathName);

        $info = pathinfo($this->pathName);
        $this->path = $info['dirname'] ?? '';
        $this->baseName = $info['basename'] ?? '';
        $this->extension = $info['extension'] ?? '';
        $this->fileName = $info['filename'] ?? '';
        $this->isFile = is_file($this->pathName);

        if ($this->isFile) {
            $p = $this->pathName;
            $this->perms = substr(sprintf('%o', fileperms($p)), -4);
            $this->ATime = fileatime($p);
            $this->CTime = filectime($p);
            $this->MTime = filemtime($p);
            $this->size = filesize($p);
            $this->isWritable = is_writable($p);
            $this->isReadable = $this->isReadble = is_readable($p);
            $this->isExecutable = is_executable($p);
        }

        return $this;
    }

    /**
     * Whether the file exists.
     *
     * @return bool
     */
    public function exists(): bool
    {
        return is_file($this->pathName);
    }

    /**
     * Set the permissions.
     *
     * @param string $permissions Four octal digits, for example "0644"
     * @return void
     * @throws SystemException When the format is wrong or chmod fails
     */
    public function setPerms($permissions)
    {
        if (!preg_match('/^[0-7]{4}$/', (string) $permissions)) {
            throw new SystemException(sprintf(_('Give four octal digits, for example 0644: %s'), $permissions));
        }
        if (!chmod($this->pathName, octdec((string) $permissions))) {
            throw new SystemException(sprintf(_('Failed to set the permissions %s'), $permissions));
        }
        $this->refresh();
    }

    /**
     * Delete the file.
     *
     * @return bool False when the file does not exist
     * @throws SystemException When it cannot be deleted
     */
    public function remove()
    {
        if (!$this->exists()) {
            return false;
        }
        if (!unlink($this->pathName)) {
            throw new SystemException(_('Failed to remove the file.'));
        }
        $this->refresh();
        return true;
    }

    /**
     * The whole content.
     *
     * @return string
     * @throws SystemException When the file cannot be read
     */
    public function read(): string
    {
        $content = $this->exists() ? file_get_contents($this->pathName) : false;
        if ($content === false) {
            throw new SystemException(sprintf(_('Failed to read the file %s'), $this->pathName));
        }
        return $content;
    }

    /**
     * Write the content (creates missing folders and the file).
     *
     * @param string $data
     * @param bool   $append True to add to the end
     * @return int Bytes written
     * @throws SystemException When it cannot be written
     */
    public function put(string $data, bool $append = false): int
    {
        $this->makeFolder($this->path);

        $bytes = file_put_contents($this->pathName, $data, $append ? FILE_APPEND | LOCK_EX : LOCK_EX);
        if ($bytes === false) {
            throw new SystemException(sprintf(_('Failed to write the file %s'), $this->pathName));
        }
        $this->refresh();
        return $bytes;
    }

    /**
     * Older name for put().
     *
     * @param string $data
     * @return int|false
     */
    public function writeaAString($data)
    {
        return $this->put((string) $data);
    }

    /**
     * Older name for read().
     *
     * @return string|false
     */
    public function readingAString()
    {
        return $this->exists() ? $this->read() : false;
    }

    /**
     * The content as lines (with line endings).
     *
     * @return string[]
     */
    public function reading()
    {
        return $this->exists() ? (file($this->pathName) ?: []) : [];
    }

    /**
     * Lines for insert(), insertBefore() and insertAfter().
     *
     * @param string[] $data
     * @return $this
     */
    public function write(array $data)
    {
        $this->write = $data;
        return $this;
    }

    /**
     * Create the file with the lines from write(), if it does not exist.
     *
     * @return bool True when the file was created
     */
    public function insert()
    {
        if ($this->exists()) {
            return false;
        }

        $this->put($this->write === [] ? '' : implode("\n", $this->write) . "\n");
        return true;
    }

    /**
     * Add the lines from write() before every line that contains $where.
     *
     * @param string $where Text to look for (case-insensitive)
     * @return $this
     */
    public function insertBefore($where)
    {
        return $this->insertAt((string) $where, true);
    }

    /**
     * Add the lines from write() after every line that contains $where.
     *
     * @param string $where Text to look for (case-insensitive)
     * @return $this
     */
    public function insertAfter($where)
    {
        return $this->insertAt((string) $where, false);
    }

    /**
     * Rename the file in its folder.
     *
     * @param string $name New name, without folder
     * @return bool
     * @throws \InvalidArgumentException When the name contains a folder
     */
    public function rename($name)
    {
        if (str_contains((string) $name, '/') || str_contains((string) $name, '\\')) {
            throw new \InvalidArgumentException(_('rename() takes a name; use move() to change the folder'));
        }

        return $this->move($this->path . '/' . $name);
    }

    /**
     * Move the file to another path in the project.
     *
     * @param string $path
     * @return bool
     * @throws \RuntimeException When the target is outside the project
     */
    public function move($path)
    {
        $target = Filesystem::resolve((string) $path);

        if (!$this->exists()) {
            return false;
        }
        $this->makeFolder(dirname($target));
        if (!rename($this->pathName, $target)) {
            return false;
        }

        $this->pathName = $target;
        $this->refresh();
        return true;
    }

    /**
     * Copy the file. A name without folder is copied into the same folder.
     *
     * @param string $name Target name or path
     * @return bool
     * @throws SystemException When it cannot be copied
     * @throws \RuntimeException When the target is outside the project
     */
    public function copy($name)
    {
        if (!$this->exists()) {
            return false;
        }

        $name = (string) $name;
        $target = (str_contains($name, '/') || str_contains($name, '\\'))
            ? Filesystem::resolve($name)
            : Filesystem::resolve($this->path . '/' . $name);

        $this->makeFolder(dirname($target));
        if (!copy($this->pathName, $target)) {
            throw new SystemException(_('Failed to copy the file.'));
        }
        return true;
    }

    /**
     * Add the lines from write() around every line that contains $where.
     *
     * @param string $where
     * @param bool   $before
     * @return $this
     * @throws SystemException When the file cannot be read or written
     */
    private function insertAt(string $where, bool $before)
    {
        $lines = $this->reading();
        $insert = array_map(fn($line) => rtrim($line, "\r\n") . "\n", $this->write);
        $result = [];
        $changed = false;

        foreach ($lines as $line) {
            $match = $where !== '' && stripos($line, $where) !== false;

            if ($match && $before) {
                array_push($result, ...$insert);
            }
            if ($match && !$before && !str_ends_with($line, "\n")) {
                $line .= "\n";
            }
            $result[] = $line;
            if ($match && !$before) {
                array_push($result, ...$insert);
            }
            $changed = $changed || $match;
        }

        if ($changed) {
            $this->put(implode('', $result));
        }

        return $this;
    }

    /**
     * Create a folder and its parents when missing.
     *
     * @param string $path
     * @return void
     * @throws SystemException When it cannot be created
     */
    private function makeFolder(string $path): void
    {
        if ($path !== '' && !is_dir($path) && !mkdir($path, 0775, true) && !is_dir($path)) {
            throw new SystemException(sprintf(_('Failed to create the folder %s'), $path));
        }
    }
}
