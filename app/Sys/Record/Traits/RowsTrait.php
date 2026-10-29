<?php

namespace Sys\Record\Traits;

/**
 * Use the fetched rows like an array: foreach, count(), $record[0].
 *
 * The query runs the first time the rows are used, so a record can be
 * built with where(), orderBy(), ... and passed to a view before it runs.
 *
 * @category _("Record rows trait")
 */
trait RowsTrait
{
    /**
     * Run the query if it has not run yet.
     *
     * @return void
     */
    private function load(): void
    {
        if (!$this->hasRunSQL) {
            $this->exec();
        }
    }

    public function offsetExists($offset): bool
    {
        $this->load();
        return isset($this->data[$offset]);
    }

    public function offsetGet($offset): mixed
    {
        $this->load();
        return $this->data[$offset] ?? null;
    }

    public function offsetSet($offset, $value): void
    {
        if ($offset === null) {
            $this->data[] = $value;
        } else {
            $this->data[$offset] = $value;
        }
    }

    public function offsetUnset($offset): void
    {
        unset($this->data[$offset]);
    }

    public function current(): mixed
    {
        return $this->data[$this->position] ?? null;
    }

    public function key(): int
    {
        return $this->position;
    }

    public function next(): void
    {
        ++$this->position;
    }

    public function rewind(): void
    {
        $this->position = 0;
        $this->load();
    }

    public function valid(): bool
    {
        $this->load();
        return isset($this->data[$this->position]);
    }

    public function count(): int
    {
        $this->load();
        return count($this->data);
    }

    /**
     * Print the rows (debugging).
     *
     * @return void
     */
    public function print_r()
    {
        $this->load();
        print_r($this->data);
    }
}
