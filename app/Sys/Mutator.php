<?php
/**
 * Class Mutator for handling data mutation.
 *
 * This class provides methods to mutate data based on mutator functions
 * provided by the user. It supports mutating both single-dimensional and
 * multi-dimensional arrays.
 * 
 * @category _("Mutator class")
 */
class Mutator
{
    private array $_data = [];
    private $_type = "";
    private array $_mutator = [];

    /**
     * Constructor for Mutator class.
     * Initializes the data and type.
     * 
     * @param array $data The initial data array.
     * @param string $type The type of data to mutate ('multi' for multi-dimensional arrays).
     */
    public function __construct(array $data = [], string $type = "")
    {
        $this->_data = $data;
        $this->_type = $type;
    }

    /**
     * Sets the data and type for Mutator instance.
     * 
     * @param array $data The data array.
     * @param string $type The type of data to mutate ('multi' for multi-dimensional arrays).
     */
    public function setdata(array $data = [], string $type = "")
    {
        $this->_data = $data;
        $this->_type = $type;
    }

    /**
     * Adds an attribute mutator function for a specific key.
     * 
     * @param string $key The key to which the mutator function is associated.
     * @param callable $func The mutator function to apply.
     * @return $this
     */
    public function attr(string $key, callable $func)
    {
        $this->_mutator[$key] = $func;
        return $this;
    }

    /**
     * Gets the mutators array.
     * 
     * @return array The array of mutator functions.
     */
    public function getMutators()
    {
        return $this->_mutator;
    }
    
    
    /**
     * Sets the mutator array.
     * 
     * @param array $funcs The array of mutator functions to set.
     */
    public function setMutators(array $funcs)
    {
        $this->_mutator = $funcs;
    }

    /**
     * Finishes the mutation process based on data type.
     * Returns the modified data.
     * 
     * @return array The modified data array.
     */
    public function finish()
    {
        if ($this->_type === 'multi') {
            $this->modifyMultiArray($this->_data);
        } else {
            $this->modifyArray($this->_data);
        }

        return $this->_data;
    }

    /**
     * Modifies a single-dimensional array using stored mutator functions.
     * 
     * @param array $array The single-dimensional array to be modified.
     */
    private function modifyArray(&$array)
    {
        foreach ($this->_mutator as $key => $func) {
            if (array_key_exists($key, $array)) {
                $array = $func($array[$key], $array);
            }
        }
    }

    /**
     * Modifies a multi-dimensional array using stored mutator functions.
     * 
     * @param array $array The multi-dimensional array to be modified.
     */
    private function modifyMultiArray(&$array)
    {
        foreach ($array as $rad => &$value) {
            foreach ($this->_mutator as $key => $func) {
                if (array_key_exists($key, $value)) {
                    $value = $func($value[$key], $value);
                }
            }
            $array[$rad] = $value;
        }
    }
}
?>
