<?php 
class getMessage extends element{
	
	public $element ="";
	private $passivmessage = false;	
		
	function __construct($options=array()){
	$this->passivmessage = true;
	$tmp = array(
            'style' => false,
            'id' => false,
            'class' => false,
            'alt'=> false,
	    'htmlform' => false
        );
	
	$this->messageoptions = $tmp;
	$msg="";
	foreach($options as $key => $value) {
	   if(array_key_exists($key, $tmp)) {
		$this->options[$key]=$value;
	   }else{
	       throw new SystemException('Argumentationsfel: (%s) finns inte i submit knapp',$key);
	   }
	}

	}
	
	public function render($text){
	$opt = $this->optionGenerator($this->messageoptions);
	if(true){
	if($this->messageoptions['htmlform']!=false && preg_match('/^%(?:\d+\$)?[dfsu]$/u',$this->messageoptions['htmlform'])!==0){
		$msg.= 	sprintf($this->messageoptions['htmlform'],$text);
			
		}else{
		$msg.= $text;
		}
	}
	return $msg;
	}

	

}


?>