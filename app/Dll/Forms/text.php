<?php 
class text extends element{
	
	public $options = array();
	public $name ="";
	public $formhtml ="";
	public $tableoptions = false;
	public $data = false;
	
	function __construct($options=array()){
		

	$tmp = array(
            'style' => false,
            'id' => false,
            'class' => false,
            'name' => false,
            'value' => false,
	    'maxlength' => false,
	    'tableoptions' => 'VARCHAR(255) NOT NULL',
	    'message' => '',
            'disabled' => false,
            'form' =>false,
            'placeholder'=> false,
            'alt'=> false,
            'label'=> false,
	    'validation' => '',
	    'html' => false
        );
	
	$this->options = $tmp;
	
	foreach($options as $key => $value) {
	   if(array_key_exists($key, $tmp)) {
		$this->options[$key]=$value;
	   }else{
	       throw new SystemException('Argumentationsfel: (%s) finns inte i fältet',$key);
	   }
	}
	
	
	if(empty($options['html'])){

		   
	}
	
	
	//$name = $this->getText($name);  
	
	
//	$this->options['value'] = $this->checksprintf($name,$this->options['value']);
	

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
     
        if($this->options['label']!=false){
            if($this->options['id']==false){
            $this->options['id'] = $this->name;
           }
            $texta = sprintf('<label for="%s">%s</label>',$this->options['id'],$this->options['label']);
           }
        if($this->data){
		$this->options['value'] = $this->data;
        }
        $opt = $this->optionGenerator($this->options);

	
        $textb= sprintf('<input type="text" %s>',$opt);
                
	if($this->options['htmlform']!==false){
		if($this->options['label']!==false){
		$text = sprintf($this->options['htmlform'],$texta,$textb);
		}else{
		$text = sprintf($this->options['htmlform'],$textb);	
		}	
	}else{
	$text = $texta.$textb;
	}		
		
	return 	$text;
	}
}


?>