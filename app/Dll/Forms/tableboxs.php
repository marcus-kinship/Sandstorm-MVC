<?
class tableboxs extends element{
	
	public $options = array();
	public $name ="";
	public $formhtml ="";
	public $tableoptions = false;
	public $data = false;
	public $rows = array();
	public $label = array();
	
	function __construct($name=array(),$rows=array(),$options=array()){
		
	if(empty($name)){
		   throw new SystemException('Argumentationsfel: finns ingen namn för fältet, Var vänlig ange namn.');
	}

	$this->options['htmlform']="";
	
	foreach($name as $as){
		
		$this->name[] = $this->names[] = $this->getText($as);;	
		
		
	}
	$tmpc = count($name); 

	$this->rows = $rows;
	$this->label = $name;
	    $x =0;
	       foreach($rows as $k => $names){
	
				$tmpname[] = $this->name[0].$k;
			

$x++;
	     }
	    
	     $this->name = $tmpname; 
	
	$tmp = array(
            'style' => false,
            'id' => false,
            'class' => false,
            'name' => $name,
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
	
	
	

	
	if(isset($options['tableoptions'])){
            $this->tableoptions = true;
        }
	

	}
	
	function render(){
		
	
     
	
     $this->options['htmlform'] .='<table>';
	     $this->options['htmlform'] .= '<tr>';
	     foreach($this->label as $k => $names){	
			$this->options['htmlform'] .= '<td>'.$names.'</td>';

	     }
	     
	
	     
	     
	     $tmpc = count($this->label);
	       $tmpw = count($this->rows);
	     $this->options['htmlform'] .= '</tr>';
	    $x =0;
	       foreach($this->rows as $k => $names){
		$this->options['htmlform'] .= '<tr>';
			$i=1;
			
			$this->options['htmlform'] .= '<td>'.$names.'</td>';
	
			while ($i < $tmpc){
			
				$this->options['htmlform'] .= '<td><input type="checkbox" name="'.$this->names[0].''.$x.'" value="'.$this->label[$i].'"></td>';
				$i++;
			
			
			}
$x++;
	     $this->options['htmlform'] .= '</tr>';
	     }
	     
	     $this->options['htmlform'] .='</table>';
    $text = $this->options['htmlform'];
     
     
        	
		
	return 	$text;
	}
}


?>