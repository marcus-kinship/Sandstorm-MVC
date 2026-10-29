<?php 
class table extends element{
	
	public $element ="";
		
	function __construct($name=""){

	}
	
	function __call($class,$args=array()){
	   $start = __dir__.'/';
	if(file_exists($start.$class.'.php')){
	
		include_once $start.$class.'.php';

		 if(class_exists($class)){
		    if(!empty($args)){
			$ref = new ReflectionClass($class);
			return $this->element[] = $ref->newInstanceArgs($args);
		    }
		    else{
			return $this->element[] = new $class();
		    }
		 }else{
			throw new SystemException('Kunde inte hitta class med det här namnet');
		 }
	}
	 throw new SystemException('Kunde inte ladda den här classen. '.$start.$class.'.php');
    }
	
	

}


?>