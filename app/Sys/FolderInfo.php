<?
class FolderInfo{

	public $isDir = false;
	public $pathName  = "";
	public $path  = "";
	public $baseName  = "";
	public $fileName = "";
	public $perms = false;
	public $CTime = false;
	public $MTime = false;
	public $ATime = false;
	public $isWritable = false;
	public $isReadble = false;
	public $type = "";
	
	protected $pointer = "";
	
	function __construct($path=""){

		if($path==""){
			throw new SystemException(_("Du måste ange en adress."));
		}

		if(is_dir($path)){
			$this->pathName = $path;
			$this->isDir = true;
			$this->setFilProperties();
		}else{
			throw new SystemException(_("Det är inte någon mapp. %s"),$path);
		}
	}
	
	 public function __toString(){
        return $this->pathName;
    }
	
	private function setFilProperties(){
		$p = $this->pathName;
		if(file_exists($p)){
		
			$this->perms = substr(sprintf('%o', fileperms($p)), -4);
			$this->ATime = fileatime($p);
			$this->CTime = filectime($p);
			$this->MTime = filemtime($p);
			$this->isWritable = is_writable($p);
			$this->isReadble = is_readable($p);
			$info = pathinfo($p);
			$this->path = $info['dirname'];
			$this->baseName = $info['basename'];
			$this->fileName = $info['filename'];
			$this->type = "dir";
		}
	}
	
	function setPerms($permissions){
		if(preg_match('/\d{4}/', $permissions, $matches, PREG_OFFSET_CAPTURE, 0)){
			if(!chmod($this->pathName,(int) $permissions)){
				throw new SystemException(_("Det gick inte sätt behörigheterna som du angett du %s"),$permissions);
			}
		}else{
			throw new SystemException(_("Du ange fel,Du måste ange fyra nummer. %s"),$permissions);
		}
	}
	
	function remove(){
		if($this->isDir){
			if(!rmdir($this->pathName)){
				throw new SystemException(_("Det gick inte ta bort mapp filen."));
			}else{
				return true;
			}
		}else{
			return false;
		}
	}
	function newFolder($name,$r=0777){
		if($this->isDir){
			$name = $this->path.DIRECTORY_SEPARATOR.$name;
			if(mkdir($name,$r)){
				return true;
			}else{
				return false;
			}
		}else{
			return false;
		}
		return $this;
	}
	
	function rename($name){
		if($this->isDir){
			$name = $this->path.DIRECTORY_SEPARATOR.$name;
			if(rename($this->pathName, $name)){
				return true;
			}else{
				return false;
			}
		}else{
			return false;
		}
		return $this;
	}
	
	function getTree(){
      $files = glob($this->pathName, GLOB_BRACE);
	      foreach ($files as $k => $dir){
	    		$files = array_merge($files, $this->getList($dir.DIRECTORY_SEPARATOR.'*'));     
	  	  }
	 return $files;

	}
	
	function getList(){
      $files = glob($this->pathName.DIRECTORY_SEPARATOR.'*', GLOB_BRACE);
      foreach ($files as $k => $dir){
    		if(is_file($filename)){
				$files[] = new FileInfo($filename); 
			}
		    
		    if(is_dir($filename)){
				$files[] = new FolderInfo($filename); 
			}   
  	 }
	 return $files;

	}
	
	function search(string $regex,int $flags = 0){
		$files  = array();
		if($this->isDir){
			$path = $this->path.DIRECTORY_SEPARATOR;
			
			if(is_file($filename)){
				$files[] = new FileInfo($filename); 
			}
		    
		    if(is_dir($filename)){
				$files[] = new FolderInfo($filename); 
			}
		}
		
		return $files;
	}
	
	function move($path){
		if($this->isDir){
			$files = $this->getTree();
			
			foreach($files as $filename) {
				$p = pathinfo($filename);
				$d = str_replace($this->path,"",$filename);
				$taget = $path.DIRECTORY_SEPARATOR.$d;
				if(!file_exists($taget) && is_dir($taget)){
					$this->makeFolder($taget);
				}
				if(is_file($taget) && !rename($filename, $path.DIRECTORY_SEPARATOR.$d)){
					return false;
				}
			}

		}else{
			return false;
		}
	}
	
	function copy($path){
		if($this->isDir){
			$files = $this->getTree();
			
			foreach($files as $filename) {
				$p = pathinfo($filename);
					$d = str_replace($this->path,"",$filename);
				$taget = $path.DIRECTORY_SEPARATOR.$d;
				if(!file_exists($taget) && is_dir($taget)){
					$this->makeFolder($taget);
				}
				if(is_file($taget) && !copy($filename, $path.DIRECTORY_SEPARATOR.$d)){
					return false;
				}
			}

		}else{
			return false;
		}
	}

	function makeFolder($path,$r=0777){

		if($this->isDir){
			if(!mkdir($this->pathName)){
				throw new SystemException(_("Det gick inte skapa mapp."));
			}else{
				return true;
			}
		}else{
			return false;
		}
    }
}