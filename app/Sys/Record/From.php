<?php

namespace Sys\Record;

use Sys\Record\Traits\FromTrait;
use Sys\Record\Traits\RowsTrait;

/**
 * Read-only Record: SELECT, where(), orderBy(), paging(), ...
 *
 * Does not load the writing code (define, save, delete, table repair).
 *
 * <code>
 * foreach ((new \Sys\Record\From('people'))->orderBy(['name' => 'ASC']) as $row) { ... }
 * </code>
 *
 * @category _("Record From class")
 */
class From extends Kernel implements \ArrayAccess, \Iterator, \Countable
{
    use FromTrait, RowsTrait;
}
