<?
class FileInfoIO{

	public $isFile = false;
	public $pathName  = "";
	public $path  = "";
	public $baseName  = "";
	public $extension  = "";
	public $fileName = "";
	public $size = false;
	public $perms = false;
	public $CTime = false;
	public $MTime = false;
	public $ATime = false;
	public $isWritable = false;
	public $isReadble = false;
	public $isExecutable = false;
	public $write = "";
	
	protected $pointer = "";
	
	function __construct($path=""){

		if($path==""){
			throw new SystemException(_("Du måste ange en adress."));
		}

		if(is_file($path)){
			$this->pathName = $path;
			$this->isFile = true;
			$this->setFilProperties();
		}else{
			throw new SystemException(sprintf(_("Det är inte någon fil. %s"),$path));
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
			$this->size = filesize($p);
			$this->isExecutable = is_executable($p);
			$info = pathinfo($p);
			$this->path = $info['dirname'];
			$this->baseName = $info['basename'];
			$this->extension = $info['extension'];
			$this->fileName = $info['filename'];
		}
	}
	
	function setPerms($permissions){
		if(preg_match('/\d{4}/', $permissions, $matches, PREG_OFFSET_CAPTURE, 0)){
			if(!chmod($this->pathName,(int) $permissions)){
				throw new SystemException(sprintf(_("Det gick inte sätt behörigheterna som du angett du %s"),$permissions));
			}
		}else{
			throw new SystemException(sprintf(_("Du ange fel,Du måste ange fyra nummer. %s"),$permissions));
		}
	}
	
	function remove(){
		if($this->isFile){
			if(!unlink($this->pathName)){
				throw new SystemException(_("Det gick inte ta bort filen."));
			}else{
				return true;
			}
		}else{
			return false;
		}
	}
	function write(array $data){
		$this->write = $data;
		return $this;
	}
	
	function rename($name){
		if($this->isFile){
			$name = $this->path."/".$name;
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
	
	function move($path){
		if($this->isFile){
		
			if(rename($this->pathName, $path)){
				return true;
			}else{
				return false;
			}
		}else{
			return false;
		}
		return $this;
	}
	
	function reading(){
		$lines  = array();
		if($this->isFile){
			$path = $this->pathName;
		
			if(!$reading = fopen($path, 'r')){
				throw new SystemException(sprintf(_("Det gick inte att läsa filen %s"),$path));
			}
			
			while (!feof($reading)) {
				$line = fgets($reading);
				$lines[] = $line;
			}
			fclose($reading);
		}
		return $lines;
	}
	
	function copy($name){
		if($this->isFile){
			$p = pathinfo('gh/lib.inc',PATHINFO_DIRNAME);

			if($p!="."){
				$name = $this->path."/".$name;
			}
			if(!copy($this->pathName, $name)) {
		    	throw new SystemException(_("Det gick inte ta kopiera filen."));
			}else{
				return true;
			}	
		}else{
			return false;
		}
	}

	function insert(){
		$end = false;
		$path = $this->pathName;
		
		if(!file_exists($this->path)){
			$this->makeFolder($this->path);
		}
		
		if(!file_exists($path)){
			$myfile = fopen($path, "w");
		
			foreach ($this->write as $line) {
				fwrite($myfile, $line."\n");
			}
		
			fclose($myfile);
			$end = true;
		}
		return $end;
	}

	private function makeFolder($path,$r=0777){

		$path = explode("/",$path);
		$structure = '';

		foreach ($path as $dir) {
			$structure.="/".trim($dir);
			if(!is_dir($structure) && strlen($structure>0) && !mkdir($structure, $r, true)) {
				throw new SystemException(sprintf(_("Det gick inte att skapa mappen %s"),$structure));
			}
		}

		return true;
	}
	
	private function pinsert($where){
	
		$path = $this->pathName;
		
		if(!$reading = fopen($path, 'r')){
			throw new SystemException(sprintf(_("Det gick inte att läsa filen %s"),$path));
		}
			
		if(!$writing = fopen($path.'.tmp', 'w')){
			throw new SystemException(_("Det gick inte att skriva till disken"));
		}
	
		$replaced = false;
		
		if($this->pointer == "before"){
			$tmp = "";
			while (!feof($reading)) {
				$line = fgets($reading);
			
				if (stristr($line,$where)) {
			
					foreach ($this->write as $v) {
						$tmp.= $v."\n".$line;
					}
						
					$replaced = true;
				}
			
				fputs($writing, $line);
			}
			
		}
		
		if($this->pointer == "after"){
			
			while (!feof($reading)) {
				$line = fgets($reading);
			
				if (stristr($line,$where)) {
			
					foreach ($this->write as $v) {
						$line.= "\n".$v;
					}
					$replaced = true;
				}
				fputs($writing, $line);
			}
		}

		fclose($reading);
		fclose($writing);
	
		if($replaced){
			rename($path.'.tmp', $path);
		} else {
			unlink($path.'.tmp');
		}
	
		return $this;
	}
	
	function insertBefore($where){
	
		$this->pointer = "before";
		$this->insert($where);
		return $this;
	}

	function insertAfter($where){
		
		$this->pointer = "after";
		$this->insert($where);
		return $this;
	}
}