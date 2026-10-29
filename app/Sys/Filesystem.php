<?php


/**
 * Filesystem class for managing file and folder operations.
 *
 * This class provides methods for handling files and folders, such as creating folders,
 * writing to files, and inserting content into files.
 *
 * @category _("Filesystem class")
 */
class Filesystem
{
    
    public $path = "";
    
    /**
     * Constructor for the Filesystem class.
     *
     * @param string $path The path to the file or folder.
     * @param string $search The search pattern if looking for files in a folder.
     */
    function __construct($path = "", $search = ""){
        
        $p = array();
        if($path != ""){
            throw new SystemException("You must provide an address.");
        }
        
        if(file_exists($path)){
            if(is_dir($path)){
              
              $f = glob($path . $search); 
              foreach($f as $k => $p){
                if(is_dir($p)){
                    $p[] = new FolderInfo($p);
                }else{
                    $p[] = new FileInfo($p);
                }
              }    
            }
            if(is_file($path)){
              return new FileInfo($p);
            }
            
        }else{
            if(is_dir($p)){
                return new FolderInfo($p);
            }else{
               return new FileInfo($p);
            }
        }
    }
    
    /**
     * Write data to the filesystem object.
     *
     * @param array $data The data to write.
     * @return $this The current object for method chaining.
     */
    function write(array $data){
        $this->write = $data;
        return $this;
    }
    
    /**
     * Create a new file with a given name and write data to it.
     *
     * @param string $name The name of the file to create.
     * @return bool True if the file creation was successful, false otherwise.
     */
    function theme($name){
        $end = false;
        if($this->path){
           $path = $this->path; 
              if($path[strlen($path) - 1] == '/'){
		            $path = substr($path, 0, -1);
		      }
		       if(!file_exists($path)){
    
                    $this->makeFolder($path);
                }
		         $path = $path . DIRECTORY_SEPARATOR . trim($name, '/');
        }else{
           
           $path = dirname($name);
           if(!file_exists($path)){
    
            $this->makeFolder($path);
            }
            $path = $name;
        }
        
         if(!file_exists($path)){
            $myfile = fopen($path, "w");
             
             foreach ($this->write as $line) {
               fwrite($myfile, $line . "\n");
             }
             
            fclose($myfile);
             $end = true;
         }
        
        return $end;
        
    }
    
    /**
     * Create a folder recursively.
     *
     * @param string $path The path of the folder to create.
     * @param int $r The permissions for the created folder.
     * @return bool True if the folder creation was successful, false otherwise.
     */
    function makeFolder($path, $r = 0777){
        
        $path = explode("/", $path);
        $structure = '';
      
        foreach ($path as $dir) {
           $structure .= "/" . trim($dir);    
            if(!is_dir($structure) && strlen($structure > 0) && !mkdir($structure, $r, true)) {
                throw new SystemException(_("Failed to create the folder %s"), $structure);
            }
        }
        
        return true;
    }
    
    /**
     * Open a file or folder with the given name.
     *
     * @param string $name The name of the file or folder to open.
     * @return $this The current object for method chaining.
     */
    function open($name){
        if($this->path){
           $path = $this->path; 
              if($path[strlen($path) - 1] == '/'){
		            $path = substr($path, 0, -1);
		      }
		 
		       $path = $path . DIRECTORY_SEPARATOR . trim($name, '/');
        }else{
           $path = $name;
        }
        $this->path = $path;
        
        return $this;
    }
    
    /**
     * Insert data after a specified position in the file.
     *
     * @param string $where The position where the data should be inserted.
     * @param mixed $data The data to insert.
     * @return $this The current object for method chaining.
     */
    function insertAfter($where, $data){
        if(file_exists($this->path)){
            
        $reading = fopen($this->path, 'r');
        $writing = fopen($this->path . '.tmp', 'w');
            
            
    $replaced = false;

    while (!feof($reading)) {
      $line = fgets($reading);
      
      if (stristr($line, $where)) {
          
            if(is_array($data)){
           foreach ($data as $v) {
               $line .= "\n" . $v;
           }
      }else{
           $line .= $data;
        
      }
        
       $replaced = true;
      }
    
      fputs($writing, $line);
    }
    
    fclose($reading); 
    fclose($writing);
    
    // might as well not overwrite the file if we didn't replace anything
    if ($replaced) 
    {
      rename($this->path . '.tmp', $this->path);
    } else {
      unlink($this->path . '.tmp');
    }
         
            
        }else{
           throw new SystemException(_("Failed to read the file because the address is wrong or the file is write-protected %s"), $this->path); 
        }

        
        return $this;
    }

}
?>
