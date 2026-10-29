<?php

namespace Dll\User;

/**
 * A user's media (images, ...) in the media table.
 *
 * Table media: id, images (path in the project), position, imageid (what the
 * media belongs to), type (one of the setting "media types", default image).
 *
 * <code>
 * $media = \User::media($postId);                   // or new \Dll\User\Media($postId, 'image')
 * $media->save([['adress' => 'upload/user/ada/images/a.jpg', 'position' => 1]]);
 * foreach ($media->get() as $item) { echo $item->image; }
 * \Dll\User\Media::position([12 => 1, 15 => 2]);    // media id => new position
 * \Dll\User\Media::remove($postId, 12);             // row and files
 * </code>
 *
 * Every query uses placeholders, and remove() only deletes files inside the
 * logged-in user's own folder upload/user/<username>/.
 *
 * @category _("Media class")
 */
class Media
{
    /** @var string Path of the file */
    public $image = "";

    /** @var string Media type */
    public $type = "";

    /** @var int|string Position in the list */
    public $position = "";

    /** @var int|string|null What the media belongs to */
    public $imageid = "";

    /** @var int|string Row id */
    public $id = "";

    /**
     * @param int|null    $imageid What the media belongs to
     * @param string|null $type    One of the setting "media types" (default: the first)
     * @throws \InvalidArgumentException For a type that is not allowed
     */
    public function __construct($imageid = null, $type = null)
    {
        $types = self::types();

        $this->imageid = $imageid;
        $this->type = $type ?? $types[0];

        if (!in_array($this->type, $types, true)) {
            throw new \InvalidArgumentException(sprintf(_('Invalid media type "%s"'), $this->type));
        }
    }

    /**
     * Add media.
     *
     * @param array $array List of ['adress' => path, 'position' => int]
     * @return int Number of rows added
     */
    public function save($array)
    {
        $rows = [];
        $args = [];
        foreach ((array) $array as $item) {
            if (!isset($item['adress']) || $item['adress'] === '') {
                continue;
            }
            $rows[] = '(%s, %d, %d, %s)';
            array_push($args, (string) $item['adress'], (int) ($item['position'] ?? 0), (int) $this->imageid, $this->type);
        }

        if ($rows === []) {
            return 0;
        }

        self::db()->query('INSERT INTO `media` (`images`, `position`, `imageid`, `type`) VALUES ' . implode(', ', $rows), ...$args);
        return count($rows);
    }

    /**
     * The media for this imageid, in position order.
     *
     * @return Media[]
     */
    public function get()
    {
        $rows = self::db()->get('as:text')->rows(
            'SELECT * FROM `media` WHERE `imageid` = %d AND `type` = %s ORDER BY `position` ASC',
            (int) $this->imageid,
            $this->type
        );

        $list = [];
        foreach ($rows ?: [] as $row) {
            $item = new static($row['imageid'], $row['type']);
            $item->id = $row['id'];
            $item->image = $row['images'];
            $item->position = $row['position'];
            $list[] = $item;
        }

        return $list;
    }

    /**
     * Remove a media row and its files (also sizes made from it, such as a-300.jpg).
     *
     * Only files inside the logged-in user's folder upload/user/<username>/ are deleted.
     *
     * @param int $pid What the media belongs to
     * @param int $id  Media id
     * @return bool False when there is no such row
     * @throws \SystemException When nobody is logged in
     */
    public static function remove($pid, $id)
    {
        $db = self::db();
        $row = $db->get('as:text')->row('SELECT * FROM `media` WHERE `id` = %d AND `imageid` = %d', (int) $id, (int) $pid);
        if (!$row) {
            return false;
        }

        $user = \User::getActiveUser();
        $own = \Filesystem::resolve('upload/user/' . basename((string) $user->username));

        $file = (string) $row['images'];
        if ($file !== '') {
            $path = \Filesystem::resolve($file);
            if (str_starts_with($path, $own . '/')) {
                $name = pathinfo($path, PATHINFO_FILENAME);
                foreach (glob(dirname($path) . '/*') ?: [] as $candidate) {
                    if (is_file($candidate) && str_starts_with(basename($candidate), $name)) {
                        unlink($candidate);
                    }
                }
            }
        }

        return $db->delete('DELETE FROM `media` WHERE `id` = %d AND `imageid` = %d', (int) $id, (int) $pid);
    }

    /**
     * Give media new positions.
     *
     * @param array $array Media id => position
     * @return void
     */
    public static function position($array)
    {
        $db = self::db();
        foreach ((array) $array as $id => $position) {
            $db->update()->row('UPDATE `media` SET `position` = %d WHERE `id` = %d', (int) $position, (int) $id);
        }
    }

    /**
     * Allowed media types (setting "media types", default ['image']).
     *
     * @return string[]
     */
    public static function types(): array
    {
        $types = \config::get('media types', true);
        return is_array($types) && $types !== [] ? array_values($types) : ['image'];
    }

    /**
     * Connection to the users' pool.
     */
    private static function db(): \DatabaseConnection
    {
        return new \DatabaseConnection(\User::$pool);
    }
}
