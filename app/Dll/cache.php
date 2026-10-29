<?

/**
 * Class Cache
 * A class for caching data in a file
 * 
 * @category _("Caching class")
 */
class Cache
{
    private $filename;

    /**
     * Constructor for Cache class
     * 
     * @param string $filename The name of the file to cache data in
     * @param bool $include Whether or not to include the cached file after writing to it
     */
    public function __construct($filename, $include = true)
    {
        $this->filename = $filename;
        $this->include = $include;
    }

    /**
     * Saves data to the cache file
     * 
     * @param callable $callback The function to generate the data to be cached
     */
    public function save(callable $callback)
    {
        $this->call = $callback;
        $this->write();
    }

    /**
     * Writes data to the cache file if it doesn't exist
     */
    function write()
    {
        if (!file_exists($this->filename)) {
            // Generate the data to be cached and write the data to the cache file
            file_put_contents($this->filename, $this->call());
        }
        // Include the cached file if the $include parameter is set to true
        if ($this->include) {
            include $this->filename;
        }
    }
}
