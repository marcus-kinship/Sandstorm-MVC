<?

/*
controller
$f = new Listview();

$f->select('*')->from('countryinfo')->limit(10);
$g = $f->table(array(
		'ISO-Numeric' => array(
			'sort' => true,
			'title' => 'iso-standard',
		),
		'Population' => array(
			'sort' => true,
			'title' => 'Befolkning',
		)
	)); 

vyn
print_r($g->render());
print $g->prevLink();
print $g->nextLink();*/


class Listview{
	public $db = null;
	public $select = "";
	public $orderby = "";
	public $selectData = array();
	public $fromData = array();
	public $from = "";
	public $type = "text";
	public $limit = "";
	public $params = "";
	public $whereData = array();
	public $where = "";
	
	
	function __construct($config = array()){

		$defaults = array(
			"db" => "default",
			"sort" => ""
		);

		$params = array_replace_recursive($defaults, $config);
		$this->params = $params;
		$this->db = new DatabaseConnection($params['db']);
		$this->conn = $this->db->getConnection();
	}
	
	function orderby(){
	    $args = func_get_args();
		$this->orderby = " ORDER BY ";
		$args = func_get_args();
   		$sql = $this->escapeString($args);
		$this->orderby .= $sql;
		return $this;
	}
	
	function select(){
		$args = func_get_args();
		$this->select = "SELECT ";
		$args = func_get_args();
   		$sql = $this->escapeString($args);
		$this->select .= $sql;
		return $this;
	}
	
	function from(){
		$args = func_get_args();
		$this->from = " FROM ";
		$this->fromData = $args;
		$this->from .= implode(",", $args);
		return $this;
	}
	
	function where(){
		$args = func_get_args();
		$this->where = " WHERE  ";
		$args = func_get_args();
   		$sql = $this->escapeString($args);
		$this->where .=  $sql;
		
		return $this;
	}
	
	  /**
   * Checkar data med (real_escape_string) och sätt ihop arrayn till text string med functionen
   * sprintf.
   *
   * @param en eller flera parametrar
   * @return string $query
   */
  public function escapeString($query) {

    if(is_array($query[0])){
        $numParams = sizeof($query[0]);
        if (!$query[0]=="") {
            for ($i = 1; $i < $numParams; $i++) {
              $query[$i] = $this->conn->real_escape_string(preg_replace('/(?:%[^a-zA-z0-9]|%$)/', '%%',$query[0][$i]));
            }
        $query[0] = $query[0][0];
        return call_user_func_array('sprintf', $query);
        }
    }else{
        $numParams = sizeof($query);
     

        if (!$query=="") {
            for ($i = 1; $i < $numParams; $i++) {

              $query[$i] = $this->conn->real_escape_string(preg_replace('/(?:%[^a-zA-z0-9]|%$)/', '%%',$query[$i]));
            }
		
	    	if($numParams>1){
			 return call_user_func_array('sprintf', $query);
		}else{
			return implode('',$query);
		}
     


        }
    }
  }
	
	function sort($fieldset="",$def=""){
		$this->params['sort'] = $fieldset;
		$this->params['sortDef'] = $def;
		return $this;
	}
	
	function limit($max){
		$offset = Request::req('page');
		$this->params['max'] = sprintf('%d', $max);
		$this->params['offset'] = sprintf('%d', $offset);
		$this->limit = sprintf(' LIMIT %d,%d',$offset,$max);
		return $this;
	}
	/**
	* Hämtar data och ange nyckar som text, nummer eller text och nummer.
	*
	* <code>
	* $db->text->function('frågan %d ,%s','data','data','etc');
	* $db->numeric->function('frågan %d ,%s','data','data','etc');
	* $db->both->function('frågan %d ,%s','data','data','etc');
	* </code>
	* @param en eller flera parametrar
	* @return mix
	*/
	public function __get($type){
		switch(strtolower($type)){

			/* associative array */
			case 'text':
			$this->type = "text";
			break;

			/* numeric array */
			case 'numeric':
			$this->type = "numeric";
			break;

			/* associative and numeric array */
			case 'both':
			$this->type = "both";
			break;

			default:
			$this->type = $type;
		}

		return $this;

	}
  
	function getData(){
		if($this->limit){
			$p = new Pagination($this->params['max']);
			$p->currentPage($this->params['offset']);
			$f = $this->db->text->get()->Row($this->select.', count(*) as cnt'.$this->from.$this->where);
			$p->num_rows($f['cnt']);

			if($this->params['sort']!=""){

				$p->setOrder($this->params['sort'],$this->params['sortDef']);	
			
			
			}
			$data = $this->db->{$this->type}->getRows($this->select.$this->from.$this->where.' '.$p->getOrder().' '.$p->limit());	
			$this->p = $p;
		}else{
			$data = $this->db->{$this->type}->getRows($this->select.$this->from.' '.$this->where);
			$this->p = false;
		}
 
		return $data;
	}
  
	function table($opts=array()){
		// todo sort 
		$sort = array();
		$sort1 = array();
		$i = 0;
		foreach($opts as $title => $data){
				
			if(array_key_exists('sort', $data) && $data['sort'] ==true){
				$sort[$i] = array(
					'_sort' => array('desc'=>$i)
				);
				$sort[$title] = $i;
				$sort1[$i] = $title;
				$i++;
			}
					
				
		}
		
		if(Request::req('asc') !== false){
			$sort[Request::req('asc')]['_sort'] = array('desc'=>Request::req('asc'));
		}
		if(Request::req('desc') !== false){
			$sort[Request::req('desc')]['_sort'] = array('asc'=>Request::req('desc'));
		}
	
		$this->sort($sort1);
  		
		$n = new rtable($opts,$this->getData());
		$n->sort = $sort;
		$n->p = $this->p;
		return $n;
	}

}