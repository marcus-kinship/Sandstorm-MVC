<?php

namespace Dll\Validation;

/**
 * Check and store uploaded files.
 *
 * <code>
 * $upload = new \Dll\Validation\UploadedFiles('send');      // name of the submit button
 * $upload->field = 'photos';                                 // <input type="file" name="photos[]" multiple>
 * $upload->path = 'upload/user/ada/images';                  // folder in the project
 * $upload->maxsize = 5 * 1024 * 1024;                        // bytes per file
 * $upload->maxfiles = 10;
 * $upload->extension = ['jpg', 'jpeg', 'png', 'webp'];
 * $files = $upload->getFiles();                              // [['tmp_name' => original name, 'name' => new path, 'size' => ...]]
 * if ($upload->errors) { ... }
 * </code>
 *
 * Safe on purpose:
 * - the file's real content type (finfo) must match its extension, so a PHP
 *   file called photo.jpg is refused; images must also open as images
 * - the extension must be in $extension, and never a script extension (php, phtml, ...)
 * - the file gets a new random name; the visitor's name is never used on disk
 * - $path must be inside the project
 * - only files PHP received as uploads are moved (is_uploaded_file)
 *
 * @category _("Upload class")
 */
class UploadedFiles
{
    /** @var string|null The last error (older code reads this) */
    public $error;

    /** @var string[] All errors */
    public $errors = [];

    /** @var string Name of the submit button that must be posted */
    public $submit;

    /** @var string Name of the file field */
    public $field = 'file';

    /** @var string Folder in the project for the files */
    public $path = 'upload';

    /** @var int Largest file in bytes (default 2 MB) */
    public $maxsize = 2097152;

    /** @var int|string Most files at once ('' = no limit) */
    public $maxfiles = '';

    /** @var string[] Allowed extensions */
    public $extension = ['jpg', 'jpeg', 'png', 'gif', 'webp'];

    /** @var string|null Message after a successful upload */
    public $success;

    /** @var string Message to use as $success */
    public $message = '';

    /** @var array Stored files */
    private array $files = [];

    /** @var bool True after valid() ran */
    private bool $done = false;

    /** Content types each extension may have (finfo) */
    private const MIME = [
        'jpg' => ['image/jpeg'], 'jpeg' => ['image/jpeg'], 'png' => ['image/png'], 'gif' => ['image/gif'],
        'webp' => ['image/webp'], 'avif' => ['image/avif'], 'bmp' => ['image/bmp', 'image/x-ms-bmp'],
        'svg' => ['image/svg+xml'], 'pdf' => ['application/pdf'], 'txt' => ['text/plain'],
        'csv' => ['text/csv', 'text/plain', 'application/csv'], 'zip' => ['application/zip'],
        'mp3' => ['audio/mpeg'], 'mp4' => ['video/mp4'], 'webm' => ['video/webm', 'audio/webm'],
        'docx' => ['application/vnd.openxmlformats-officedocument.wordprocessingml.document', 'application/zip'],
        'xlsx' => ['application/vnd.openxmlformats-officedocument.spreadsheetml.sheet', 'application/zip'],
    ];

    /** Extensions that are never stored, whatever $extension says */
    private const NEVER = ['php', 'php3', 'php4', 'php5', 'php7', 'php8', 'phtml', 'phar', 'pht', 'phps', 'cgi', 'pl', 'py', 'sh', 'exe', 'htaccess', 'htm', 'html', 'js'];

    /**
     * @param string $submit Name of the submit button that must be in $_POST
     */
    public function __construct($submit = '')
    {
        if ($submit === '' || $submit === null) {
            $this->error = _('Choose a submit button');
            $this->errors[] = $this->error;
        }
        $this->submit = (string) $submit;
    }

    /**
     * Check and store the uploaded files (runs once).
     *
     * @return bool True when at least one file was stored and nothing failed
     */
    public function valid()
    {
        if ($this->done) {
            return $this->files !== [] && $this->errors === [];
        }
        $this->done = true;

        if ($this->submit === '' || !isset($_POST[$this->submit]) || !isset($_FILES[$this->field])) {
            return false;
        }

        $files = self::normalize($_FILES[$this->field]);

        if ($this->maxfiles !== '' && count($files) > (int) $this->maxfiles) {
            return $this->fail(sprintf(_('You can upload at most %d files'), (int) $this->maxfiles));
        }

        try {
            $folder = \Filesystem::resolve($this->path);
        } catch (\RuntimeException $e) {
            return $this->fail(_('The upload folder is outside the project'));
        }
        if (!is_dir($folder) && !mkdir($folder, 0775, true) && !is_dir($folder)) {
            return $this->fail(_('Could not create the upload folder'));
        }

        foreach ($files as $file) {
            $this->store($file, $folder);
        }

        if ($this->files !== [] && $this->errors === []) {
            $this->success = $this->message !== '' ? _($this->message) : _('The files were uploaded');
        }

        return $this->files !== [] && $this->errors === [];
    }

    /**
     * The stored files (runs valid() first).
     *
     * @return array<int, array{tmp_name: string, name: string, size: int, type: string}>
     *         tmp_name is the visitor's file name, name the stored path in the project
     */
    public function getFiles()
    {
        $this->valid();
        return $this->files;
    }

    /**
     * Check one file and move it into the folder.
     */
    private function store(array $file, string $folder): void
    {
        $original = basename((string) ($file['name'] ?? ''));

        if (($file['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) {
            if (($file['error'] ?? null) !== UPLOAD_ERR_NO_FILE) {
                $this->fail(sprintf('%s: %s', $original, self::codeToMessage((int) $file['error'])));
            }
            return;
        }

        $tmp = (string) ($file['tmp_name'] ?? '');
        if (!is_uploaded_file($tmp)) {
            $this->fail(sprintf(_('%s was not uploaded'), $original));
            return;
        }

        $size = (int) filesize($tmp);
        if ($size > (int) $this->maxsize) {
            $this->fail(sprintf(_('%s is larger than %s'), $original, self::bytes((int) $this->maxsize)));
            return;
        }

        $extension = strtolower(pathinfo($original, PATHINFO_EXTENSION));
        $allowed = array_map('strtolower', (array) $this->extension);
        if ($extension === '' || in_array($extension, self::NEVER, true) || !in_array($extension, $allowed, true)) {
            $this->fail(sprintf(_('%s: only these file types are allowed: %s'), $original, implode(', ', $allowed)));
            return;
        }

        $mime = self::realType($tmp);
        if (isset(self::MIME[$extension]) && !in_array($mime, self::MIME[$extension], true)) {
            $this->fail(sprintf(_('%s is not a real %s file'), $original, $extension));
            return;
        }
        if (str_starts_with($mime, 'image/') && $mime !== 'image/svg+xml' && @getimagesize($tmp) === false) {
            $this->fail(sprintf(_('%s is not a real image'), $original));
            return;
        }

        $name = bin2hex(random_bytes(16)) . '.' . $extension;
        if (!move_uploaded_file($tmp, $folder . '/' . $name)) {
            $this->fail(sprintf(_('%s could not be stored'), $original));
            return;
        }
        @chmod($folder . '/' . $name, 0644);

        $this->files[] = [
            'tmp_name' => $original,
            'name' => rtrim(str_replace('\\', '/', $this->path), '/') . '/' . $name,
            'size' => $size,
            'type' => $mime,
        ];
    }

    /**
     * $_FILES for one field as a list of files (also for name="x[]").
     *
     * @param array $field
     * @return array<int, array{name: string, type: string, tmp_name: string, error: int, size: int}>
     */
    private static function normalize(array $field): array
    {
        if (!is_array($field['name'] ?? null)) {
            return [$field];
        }

        $files = [];
        foreach (array_keys($field['name']) as $i) {
            $files[] = [
                'name' => $field['name'][$i] ?? '',
                'type' => $field['type'][$i] ?? '',
                'tmp_name' => $field['tmp_name'][$i] ?? '',
                'error' => $field['error'][$i] ?? UPLOAD_ERR_NO_FILE,
                'size' => $field['size'][$i] ?? 0,
            ];
        }

        return $files;
    }

    /**
     * The file's real content type, read from its bytes (not what the browser said).
     */
    private static function realType(string $file): string
    {
        if (!class_exists('finfo')) {
            return '';
        }
        return (string) (new \finfo(FILEINFO_MIME_TYPE))->file($file);
    }

    /**
     * Message for an upload error code.
     */
    private static function codeToMessage(int $code): string
    {
        return match ($code) {
            UPLOAD_ERR_INI_SIZE => _('The file is larger than the server allows'),
            UPLOAD_ERR_FORM_SIZE => _('The file is larger than the form allows'),
            UPLOAD_ERR_PARTIAL => _('The file was only partly uploaded'),
            UPLOAD_ERR_NO_FILE => _('No file was uploaded'),
            UPLOAD_ERR_NO_TMP_DIR => _('The server has no temporary folder'),
            UPLOAD_ERR_CANT_WRITE => _('The file could not be written to disk'),
            UPLOAD_ERR_EXTENSION => _('A PHP extension stopped the upload'),
            default => _('Unknown upload error'),
        };
    }

    /**
     * "2 MB" for 2097152.
     */
    private static function bytes(int $bytes): string
    {
        return $bytes >= 1048576 ? round($bytes / 1048576, 1) . ' MB' : round($bytes / 1024) . ' KB';
    }

    /**
     * Note an error.
     */
    private function fail(string $message): bool
    {
        $this->error = $message;
        $this->errors[] = $message;
        return false;
    }
}
