<?
	class extendDatabasRouter {
		
		public $table;
		
		function __construct($table = 'siterouter') {
			$this->table = $table;
		}
		
		function getPageBySlug($slug=""){
			// ^(user|%s)/(ujuj|%s)/(friends|%s)/(quickmove|%s)$
		 $db = new DatabaseConnection();
			$pieces = explode("/", $slug);
			foreach($pieces as $s => $v){
				if(is_numeric($v)){
					$pieces[$s] = '('.$v.'|%d)';
				}else{
					$pieces[$s] = '('.$v.'|%s)';
				}
			}
		
			return $record = $db->text->getSingleRow('SELECT * FROM '.$this->table.' WHERE pattern REGEXP  "%s"','^'.join('/',$pieces).'$');
			
		}
		
		function getPageById($id=""){
			$db = new DatabaseConnection();
			return	$record = $db->text->getSingleRow('SELECT * FROM '.$this->table.' WHERE id = "%d"',$id);
			
		}
		
		function savePage($slug,$class,$function,$extend="",$path=""){
		 
			$s = new Record($this->table);
			$s->pattern = $slug;
			$s->class = $class;
			$s->function = $function;
			$s->extend = $extend;
			$s->path = $path;
			$s->time = date("Y-m-d H:i:s");
			$id = $s->save();
			unset($s);
			return $id;
		}
		
		function getPages($options = array()){
			
		}
	}

?>