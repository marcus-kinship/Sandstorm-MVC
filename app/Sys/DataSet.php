<?php

/**
 * DataSet class for managing data as an array.
 *
 * This class implements ArrayAccess, Iterator, and Countable interfaces
 * to allow array-like access, iteration, and counting of the stored data.
 *
 * It also allows adding dynamic functions (closures or callables) that can
 * access and modify the dataset object directly.
 *
 * @category _("Data Handling Class")
 */
class DataSet implements \ArrayAccess, \Iterator, \Countable
{
    /**
     * @var array Container for actual data values
     */
    private $container = [];

    /**
     * @var array Array of keys for iteration
     */
    private $keys = [];

    /**
     * @var int Current position for iteration
     */
    private $position = 0;

    /**
     * @var array Store of dynamic functions (closures or callables)
     */
    private $functions = [];

    /**
     * Constructor
     *
     * @param array $data Initial data to populate the dataset
     */
    public function __construct(array $data = [])
    {
        $this->container = $data;
        $this->keys = array_keys($this->container);
        $this->position = 0;
    }

    /**
     * Add a dynamic function (Closure or callable)
     * 
     * Closures will be bound to this DataSet instance, allowing
     * direct access to internal data via $this->key.
     *
     * @param string $name Name of the function
     * @param Closure|callable $callable The function to add
     * @throws \InvalidArgumentException If parameter is not a closure or callable
     */
    public function addFunction($name, $callable)
    {
        if ($callable instanceof \Closure) {
            // Bind closure to this DataSet instance (data-aware)
            $this->functions[$name] = $callable->bindTo($this, $this);
        } elseif (is_callable($callable)) {
            // Regular callable (function or [class, method])
            $this->functions[$name] = $callable;
        } else {
            throw new \InvalidArgumentException("Parameter must be a Closure or callable");
        }
    }

    /**
     * Call a dynamic function
     *
     * @param string $name Name of the function
     * @param array $arguments Arguments to pass to the function
     * @return mixed Result of the function call
     * @throws \Exception if function does not exist
     */
    public function __call($name, $arguments)
    {
        if (array_key_exists($name, $this->functions)) {
            return $this->functions[$name](...$arguments);
        }

        throw new \Exception(sprintf("Function %s does not exist", $name));
    }

    /**
     * Get a value by key (safe, returns null if key does not exist)
     *
     * @param string $key Key to retrieve
     * @return mixed Reference to the value, or null if not found
     */
    public function &__get($key)
    {
        if (!array_key_exists($key, $this->container)) {
            $null = null;
            return $null;
        }
        return $this->container[$key];
    }

    /**
     * Set a value by key
     *
     * @param string $key The key to set
     * @param mixed $value The value to assign
     */
    public function __set($key, $value)
    {
        $this->container[$key] = $value;
        $this->keys = array_keys($this->container);
    }

    /**
     * Check if a key exists
     *
     * @param string $key The key to check
     * @return bool True if key exists, false otherwise
     */
    public function __isset($key)
    {
        return isset($this->container[$key]);
    }

    /**
     * Countable interface: return number of elements
     *
     * @return int Number of elements
     */
    public function count()
    {
        return count($this->keys);
    }

    /**
     * Rewind the iterator to the beginning
     */
    public function rewind(): void
    {
        $this->position = 0;
    }

    /**
     * Get the current value in the iteration
     *
     * @return mixed Current value, or null if invalid
     */
    public function current(): mixed
    {
        return $this->valid() ? $this->container[$this->keys[$this->position]] : null;
    }

    /**
     * Get the current key in the iteration
     *
     * @return mixed Current key, or null if invalid
     */
    public function key(): mixed
    {
        return $this->valid() ? $this->keys[$this->position] : null;
    }

    /**
     * Move to the next element in iteration
     */
    public function next(): void
    {
        ++$this->position;
    }

    /**
     * Check if current position is valid
     *
     * @return bool True if valid, false otherwise
     */
    public function valid(): bool
    {
        return isset($this->keys[$this->position]);
    }

    /**
     * ArrayAccess: Set value at offset
     *
     * @param mixed $offset The key or index
     * @param mixed $value The value to set
     */
    public function offsetSet($offset, $value): void
    {
        if (is_null($offset)) {
            $this->container[] = $value;
        } else {
            $this->container[$offset] = $value;
        }
        $this->keys = array_keys($this->container);
    }

    /**
     * ArrayAccess: Check if offset exists
     *
     * @param mixed $offset The key to check
     * @return bool True if exists, false otherwise
     */
    public function offsetExists(mixed $offset): bool
    {
        return isset($this->container[$offset]);
    }

    /**
     * ArrayAccess: Remove value at offset
     *
     * @param mixed $offset The key to remove
     */
    public function offsetUnset($offset): void
    {
        unset($this->container[$offset]);
        $index = array_search($offset, $this->keys, true);
        if ($index !== false) {
            unset($this->keys[$index]);
            $this->keys = array_values($this->keys);
        }
    }

    /**
     * ArrayAccess: Get value at offset
     *
     * @param mixed $offset The key to retrieve
     * @return mixed Value, or null if not set
     */
    public function offsetGet($offset): mixed
    {
        return isset($this->container[$offset]) ? $this->container[$offset] : null;
    }

    /**
     * Convert the dataset to an array
     *
     * @return array Copy of dataset as an array
     */
    public function toArray()
    {
        return $this->container;
    }

    /**
     * Convert the dataset to JSON
     *
     * @return string JSON-encoded dataset
     */
    public function toJSON()
    {
        return json_encode($this->toArray());
    }

    /**
     * Print dataset contents for debugging
     */
    public function debug()
    {
        print_r($this->container);
    }
}