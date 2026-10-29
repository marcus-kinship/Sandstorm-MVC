<?
class dropmenu extends element{
	
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
	    'options' => array(),
	    'message' => '',
	    'tableoptions' => 'VARCHAR(255) NOT NULL',
            'disabled' => false,
            'modevalue' =>'dynamic',
            'placeholder'=> false,
            'alt'=> false,
            'label'=> false,
	    'text'=>false,
	    'validation' => '',
	    'htmlform' => false,
	   
	    
        );
	
	foreach($options['options'] as $k => $v){
		if(is_array($v)){
			foreach($v as $kc => $vc){
				$tmp['option_nodes'][$kc]=$vc;
			}
		}else{
				$tmp['option_nodes'][$k]=$v;
			
		}
	}

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
	
	if($this->options['modevalue']=='int'){
		
	}
	
	

	if($this->data!=false){
		$this->options['value'] = $this->data;
        }
     
         if($this->options['label']!=false){
            if($this->options['id']==false){
            $this->options['id'] = $this->name;
           }
            $texta = sprintf('<label for="%s">%s</label>',$this->options['id'],$this->options['label']);
           }
     
	
     
	 $text="";
	foreach($this->options['options'] as $k => $v){
		if(is_array($v)){
			$text .= '<optgroup label="'.$k.'">';
			foreach($v as $kc => $vc){
				$text .='<option';
				if($kc == $this->options['value']){
					$text .= 'selected ';	
				}
				$text .='value="'.$kc.'">'.$vc.'</option>';
			}
			$text .= '</optgroup>';
		}else{
			
				$text .='<option ';
				if($k == $this->options['value']){
					$text .= 'selected ';	
				}
				$text .='value="'.$k.'">'.$v.'</option>';
			
		}
	}

	$this->options['text']=false;
	$tmp[0] = $this->options['modevalue'];
	$tmp[1] = $this->options['options'];
	$tmp[2] = $this->options['value'];
	$tmp[3] = $this->options['option_nodes'];
	$this->options['value']=false;
	$this->options['extends']=false;
	$this->options['modevalue']=false;
	$this->options['options'] =false;
         $this->options['option_nodes']=false;
	$opt = $this->optionGenerator($this->options);
	 
	 $this->options['modevalue'] = $tmp[0];
	$this->options['options'] = $tmp[1];

	$this->options['option_nodes'] = $tmp[3];
        $textb= sprintf('<select %s>%s</select>',$opt,$text);
                
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