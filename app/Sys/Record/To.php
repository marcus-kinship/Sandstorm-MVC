<?php

namespace Sys\Record;

use Sys\Record\Traits\ToTrait;

/**
 * Write-only Record: define(), save(), delete() and table changes.
 *
 * Does not load the reading code.
 *
 * <code>
 * $id = (new \Sys\Record\To('people'))->define('name', 'Ada')->save();
 * </code>
 *
 * @category _("Record To class")
 */
class To extends Kernel
{
    use ToTrait;
}
