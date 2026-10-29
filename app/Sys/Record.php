<?php

use Sys\Record\Kernel;
use Sys\Record\Traits\FromTrait;
use Sys\Record\Traits\RowsTrait;
use Sys\Record\Traits\ToTrait;

/**
 * The Record ORM: read and write a table.
 *
 * <code>
 * $people = new Record('people');
 * $id = $people->define('name', 'Ada')->define('age', 36)->save();
 * $ada = $people->searchInField('id', '=', $id, 'single');
 *
 * foreach ((new Record('people'))->where(['age', '>', 30]) as $row) { ... }
 *
 * class Person extends Record { protected $table = 'people'; }
 * $everyone = Person::all();
 * </code>
 *
 * Use Record\From to only read and Record\To to only write: they load less
 * code and limit what the code can do with the table.
 *
 * @category _("Record class")
 */
class Record extends Kernel implements \ArrayAccess, \Iterator, \Countable
{
    use ToTrait, FromTrait, RowsTrait;
}
