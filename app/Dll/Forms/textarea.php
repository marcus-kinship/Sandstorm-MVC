<?php 
class textarea extends element{
	
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
	    'message' => '',
	    'tableoptions' => 'TEXT NOT NULL',
            'disabled' => false,
            'form' =>false,
            'placeholder'=> false,
            'alt'=> false,
            'label'=> false,
	    'text'=>false,
	    'validation' => '',
	    'htmlform' => false,
	    'rows' => '4',
	    'cols' => '50'
        );
	
	
	$this->options = $tmp;
	
	foreach($options as $key => $value) {
	   if(array_key_exists($key, $tmp)) {      
		$this->options[$key]=$value;
	   }else{
	       throw new SystemException('Argumentationsfel: (%s) finns inte i fältet',$key);
	   }
	}
	$temp="";
	
	
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
	   
	
         $text = $this->checksprintf($this->name,$this->options['text']);
	
	$this->options['text']=false;
 
         $opt = $this->optionGenerator($this->options);
	$this->options['text'] = $text;
	if($this->data!=false){
		$textb= sprintf('<textarea %s>%s</textarea>',$opt,$this->data);
        }else{
		$textb= sprintf('<textarea %s>%s</textarea>',$opt,$text);
	}
	
	if($this->options['htmlform']!=false){
		if($this->options['label']!=false){
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