<?php

/**
 * Files and folders inside the project.
 *
 * Every path is resolved from the project folder (DOCUMENT_ROOT) and must stay
 * inside it: "../" that leads out, or an absolute path elsewhere, throws. So a
 * path from a visitor can never reach other files on the server.
 *
 * <code>
 * $file   = Filesystem::path('upload/report.pdf');     // FileInfo
 * $folder = Filesystem::path('upload');                 // FolderInfo
 * foreach ((new Filesystem('upload'))->getDir('*.jpg') as $image) { ... }
 * (new Filesystem('upload/old.txt'))->rename('upload/new.txt');
 * </code>
 *
 * @category _("File Handling class")
 */
class Filesystem
{
    /**
     * The resolved path this object works on
     *
     * @var string
     */
    protected string $source = '';

    /**
     * @param string $sources Path from the project folder (or absolute inside it)
     * @throws \RuntimeException When the path leads outside the project
     */
    public function __construct($sources)
    {
        $this->source = self::resolve((string) $sources);
    }

    /**
     * The resolved path.
     *
     * @return string
     */
    public function getSource()
    {
        return $this->source;
    }

    /**
     * FileInfo for a file, FolderInfo for a folder (FileInfo when it does not exist yet).
     *
     * @param string $sources
     * @return FileInfo|FolderInfo
     * @throws \RuntimeException When the path leads outside the project
     */
    public static function path($sources)
    {
        $path = self::resolve((string) $sources);

        return is_dir($path) ? new FolderInfo($path) : new FileInfo($path, false);
    }

    /**
     * Paths in this folder that match a pattern.
     *
     * @param string $type glob() pattern, for example '*' or '*.jpg'
     * @return string[] Full paths (empty when this is not a folder)
     */
    public function getDir($type = '*')
    {
        if (!is_dir($this->source) || str_contains((string) $type, '..')) {
            return [];
        }

        return glob(rtrim($this->source, '/') . '/' . $type) ?: [];
    }

    /**
     * pathinfo() of the file, null when it is not a file.
     *
     * @return array|null
     */
    public function getInfo()
    {
        return is_file($this->source) ? pathinfo($this->source) : null;
    }

    /**
     * Move or rename the file to another path in the project.
     *
     * @param string $name New path from the project folder
     * @return bool
     * @throws \RuntimeException When the new path leads outside the project
     */
    public function rename($name)
    {
        return is_file($this->source) && rename($this->source, self::resolve((string) $name));
    }

    /**
     * Copy the file to another path in the project.
     *
     * @param string $name Target path from the project folder
     * @return bool
     * @throws \RuntimeException When the target leads outside the project
     */
    public function copy($name)
    {
        return is_file($this->source) && copy($this->source, self::resolve((string) $name));
    }

    /**
     * Delete the file (only this file, never a pattern).
     *
     * @return bool
     */
    public function remove()
    {
        return is_file($this->source) && unlink($this->source);
    }

    /**
     * The project folder, with forward slashes and no trailing slash.
     *
     * @return string
     * @throws \RuntimeException When DOCUMENT_ROOT is not set
     */
    public static function root(): string
    {
        $root = realpath($_SERVER['DOCUMENT_ROOT'] ?? '');
        if ($root === false || $root === '') {
            throw new \RuntimeException(_('DOCUMENT_ROOT is not set to an existing folder'));
        }

        return rtrim(str_replace('\\', '/', $root), '/');
    }

    /**
     * Turn a path into a full path inside the project, or throw.
     *
     * The path does not have to exist yet (for a new file or folder); the
     * part of it that exists is resolved with realpath(), so symlinks and
     * "../" cannot lead out of the project.
     *
     * @param string $path From the project folder, or absolute inside it
     * @return string Full path with forward slashes
     * @throws \RuntimeException When the path is empty or leads outside the project
     */
    public static function resolve(string $path): string
    {
        if (trim($path) === '' || str_contains($path, "\0")) {
            throw new \RuntimeException(_('You must provide a path.'));
        }

        $root = self::root();
        $path = str_replace('\\', '/', $path);
        $absolute = str_starts_with($path, '/') || preg_match('#^[A-Za-z]:/#', $path);
        $full = $absolute ? $path : $root . '/' . $path;

        // Remove . and .. by hand, then resolve the part that exists
        $parts = [];
        foreach (explode('/', $full) as $i => $part) {
            if ($part === '..') {
                array_pop($parts);
            } elseif ($part !== '.' && ($part !== '' || $i === 0)) {
                $parts[] = $part;
            }
        }
        $full = implode('/', $parts);

        $existing = $full;
        $rest = '';
        while ($existing !== '' && $existing !== '.' && !file_exists($existing)) {
            $rest = '/' . basename($existing) . $rest;
            $existing = dirname($existing);
        }
        $real = realpath($existing);
        $resolved = ($real === false ? $existing : str_replace('\\', '/', $real)) . $rest;

        $inside = PHP_OS_FAMILY === 'Windows'
            ? stripos($resolved . '/', $root . '/') === 0
            : str_starts_with($resolved . '/', $root . '/');

        if (!$inside) {
            throw new \RuntimeException(sprintf(_('The path is outside the project: %s'), $path));
        }

        return rtrim($resolved, '/');
    }
}
