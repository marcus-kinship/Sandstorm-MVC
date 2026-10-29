<?php 
class hidden extends element{
	
	public $options = array();
	public $name ="";
	public $formhtml ="";
	public $tableoptions = false;
	public $data = false;
	
	function __construct($name="",$options=array()){
		
	if(empty($name)){
		   throw new SystemException('Argumentationsfel: finns ingen namn för fältet, Var vänlig ange namn.');
	}
	
	
	$name = $this->getText($name);  
	$tmp = array(
            'style' => false,
            'id' => false,
            'class' => false,
            'name' => false,
            'value' => false,
	    'message' => '',
	    'maxlength' => false,
	    'tableoptions' => 'VARCHAR(255) NOT NULL',
            'disabled' => false,
            'form' =>false,
            'alt'=> false,
	    'validation' => '',
	    'htmlform' => false
        );
	
	$this->options = $tmp;
	
	foreach($options as $key => $value) {
	   if(array_key_exists($key, $tmp)) {
		$this->options[$key]=$value;
	   }else{
	       throw new SystemException('Argumentationsfel: (%s) finns inte i fältet',$key);
	   }
	}
	
	
	

	if($this->options['name']==false){
            $this->options['name'] = $name;
        }
	
	if(isset($options['tableoptions'])){
            $this->tableoptions = true;
        }
	

	$this->name = $name;

	}
	
	function render(){
		
	$texta ="";
     
     
        if($this->data){
		$this->options['value'] = $this->data;
        }
	
        $opt = $this->optionGenerator($this->options);


        $textb= sprintf('<input type="hidden" %s>',$opt);
                
	if($this->options['htmlform']!==false){
		
		
		
		$text = $this->options['htmlform']=$textb;	
			
	}else{
	$text = "";
	}		
		
	return 	$text;
	}
}


?>