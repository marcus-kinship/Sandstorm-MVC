<?
class time extends element{
	
	public $options = array();
	public $name ="";
	public $formhtml ="";
	public $message = false;
		
	function __construct($name="",$options=array()){
		
	if(empty($name)){
		   throw new SystemException('Argumentationsfel: finns ingen namn för fältet, Var vänlig ange namn.');
	}
	
	
	
	$name = $this->getText($name);  
	$tmp = array(
            'time' => 'now()',
            'type' => 'TIMESTAMP',
        );
	
	$this->options = $tmp;
	
	foreach($options as $key => $value) {
	   if(array_key_exists($key, $tmp)) {
		$this->options[$key]=$value;
	   }else{
	       throw new SystemException('Argumentationsfel: (%s) finns inte i fältet',$key);
	   }
	}
	

	$this->name = $name;
	
	
	}
	
	
}


?>