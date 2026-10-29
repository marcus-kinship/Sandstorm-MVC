<?php

/**
 * A folder in the project: its properties, contents and folder operations.
 *
 * Paths are locked to the project folder (see Filesystem::resolve()).
 *
 * <code>
 * $upload = new FolderInfo('upload');
 * foreach ($upload->getList() as $item) { echo $item->baseName; }   // FileInfo and FolderInfo
 * $images = $upload->search('\.(jpg|png)$', true);                  // recursive
 * $upload->newFolder('2026');
 * $upload->copy('backup/upload');
 * </code>
 *
 * @category _("Folder Handling class")
 */
class FolderInfo
{
    /** @var bool True when the folder exists */
    public $isDir = false;

    /** @var string Full path */
    public $pathName = "";

    /** @var string Parent folder */
    public $path = "";

    /** @var string Folder name */
    public $baseName = "";

    /** @var string Folder name (same as $baseName) */
    public $fileName = "";

    /** @var string|false Permissions, for example "0755" */
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

    /** @var string Always "dir" */
    public $type = "dir";

    /**
     * @param string $path Path from the project folder, or absolute inside it
     * @throws \RuntimeException When the path is outside the project
     * @throws SystemException When it is not a folder
     */
    public function __construct($path = "")
    {
        $this->pathName = Filesystem::resolve((string) $path);

        if (!is_dir($this->pathName)) {
            throw new SystemException(sprintf(_('It is not a folder: %s'), $path));
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
        $p = $this->pathName;

        $this->isDir = is_dir($p);
        $this->path = dirname($p);
        $this->baseName = $this->fileName = basename($p);

        if ($this->isDir) {
            $this->perms = substr(sprintf('%o', fileperms($p)), -4);
            $this->ATime = fileatime($p);
            $this->CTime = filectime($p);
            $this->MTime = filemtime($p);
            $this->isWritable = is_writable($p);
            $this->isReadable = is_readable($p);
        }

        return $this;
    }

    /**
     * Set the permissions.
     *
     * @param string $permissions Four octal digits, for example "0755"
     * @return void
     * @throws SystemException When the format is wrong or chmod fails
     */
    public function setPerms($permissions)
    {
        if (!preg_match('/^[0-7]{4}$/', (string) $permissions)) {
            throw new SystemException(sprintf(_('Give four octal digits, for example 0755: %s'), $permissions));
        }
        if (!chmod($this->pathName, octdec((string) $permissions))) {
            throw new SystemException(sprintf(_('Failed to set the permissions %s'), $permissions));
        }
        $this->refresh();
    }

    /**
     * Delete the folder.
     *
     * @param bool $recursive True to delete everything in it too
     * @return bool
     * @throws SystemException When it cannot be deleted (for example not empty)
     */
    public function remove(bool $recursive = false)
    {
        if (!is_dir($this->pathName)) {
            return false;
        }

        if ($recursive) {
            $items = new RecursiveIteratorIterator(
                new RecursiveDirectoryIterator($this->pathName, FilesystemIterator::SKIP_DOTS),
                RecursiveIteratorIterator::CHILD_FIRST
            );
            foreach ($items as $item) {
                $item->isDir() && !$item->isLink() ? rmdir($item->getPathname()) : unlink($item->getPathname());
            }
        }

        if (!@rmdir($this->pathName)) {
            throw new SystemException(sprintf(_('Failed to remove the folder %s (is it empty?)'), $this->pathName));
        }

        $this->refresh();
        return true;
    }

    /**
     * Create a folder inside this folder.
     *
     * @param string $name Name of the new folder (may contain subfolders: "a/b")
     * @param int    $r    Permissions
     * @return bool True when it was created (or already existed)
     * @throws \RuntimeException When the name leads outside the project
     */
    public function newFolder($name, $r = 0775)
    {
        $target = Filesystem::resolve($this->pathName . '/' . $name);

        return is_dir($target) || mkdir($target, $r, true);
    }

    /**
     * Rename the folder in its parent folder.
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
     * All files and folders inside this folder, at every level (full paths).
     *
     * @return string[]
     */
    public function getTree()
    {
        $paths = [];
        $items = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($this->pathName, FilesystemIterator::SKIP_DOTS),
            RecursiveIteratorIterator::SELF_FIRST
        );
        foreach ($items as $item) {
            $paths[] = str_replace('\\', '/', $item->getPathname());
        }
        sort($paths);
        return $paths;
    }

    /**
     * Files and folders directly in this folder.
     *
     * @return array<int, FileInfo|FolderInfo>
     */
    public function getList()
    {
        $list = [];
        foreach (new FilesystemIterator($this->pathName, FilesystemIterator::SKIP_DOTS) as $item) {
            $path = str_replace('\\', '/', $item->getPathname());
            $list[] = $item->isDir() ? new FolderInfo($path) : new FileInfo($path);
        }
        usort($list, fn($a, $b) => strcmp($a->pathName, $b->pathName));
        return $list;
    }

    /**
     * Files (and, when not recursive, folders) whose name matches a regular expression.
     *
     * @param string $regex     Regular expression without delimiters, for example '\.class\.php$'
     * @param bool   $recursive Also look in subfolders (then files only)
     * @return array<int, FileInfo|FolderInfo>
     */
    public function search(string $regex, $recursive = false)
    {
        $pattern = '/' . str_replace('/', '\/', $regex) . '/i';
        $found = [];

        if ($recursive) {
            $items = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($this->pathName, FilesystemIterator::SKIP_DOTS));
            foreach ($items as $item) {
                if ($item->isFile() && preg_match($pattern, $item->getFilename())) {
                    $found[] = new FileInfo(str_replace('\\', '/', $item->getPathname()));
                }
            }
        } else {
            foreach ($this->getList() as $item) {
                if (preg_match($pattern, $item->baseName)) {
                    $found[] = $item;
                }
            }
        }

        usort($found, fn($a, $b) => strcmp($a->pathName, $b->pathName));
        return $found;
    }

    /**
     * Move the folder with everything in it.
     *
     * @param string $path New path from the project folder
     * @return bool
     * @throws \RuntimeException When the target is outside the project
     */
    public function move($path)
    {
        $target = Filesystem::resolve((string) $path);

        if (!is_dir($this->pathName) || file_exists($target)) {
            return false;
        }
        if (!is_dir(dirname($target))) {
            mkdir(dirname($target), 0775, true);
        }
        if (!rename($this->pathName, $target)) {
            return false;
        }

        $this->pathName = $target;
        $this->refresh();
        return true;
    }

    /**
     * Copy the folder with everything in it.
     *
     * @param string $path Target path from the project folder (created when missing)
     * @return bool
     * @throws \RuntimeException When the target is outside the project
     * @throws SystemException When a file cannot be copied
     */
    public function copy($path)
    {
        $target = Filesystem::resolve((string) $path);

        if (!is_dir($this->pathName)) {
            return false;
        }
        if (str_starts_with($target . '/', $this->pathName . '/')) {
            throw new SystemException(_('A folder cannot be copied into itself'));
        }

        if (!is_dir($target)) {
            mkdir($target, 0775, true);
        }

        foreach ($this->getTree() as $source) {
            $destination = $target . substr($source, strlen($this->pathName));
            if (is_dir($source)) {
                is_dir($destination) || mkdir($destination, 0775, true);
            } elseif (!copy($source, $destination)) {
                throw new SystemException(sprintf(_('Failed to copy %s'), $source));
            }
        }

        return true;
    }
}
