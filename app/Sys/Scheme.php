<?php

namespace Sys;

/**
 * 
 * Scheme::check($err,$sql)->create(function($table){
		$table->table('table');
		$table->key();
		$table->text(155)->name('name');
		$table->text(150)->name('last');
		$table->text(150)->name('pass');

	});	
 * @category _("Scheme class")
 */
class Scheme
{

	public $db;
	public $table = "";
	public $data = array();
	public $names = array();
	public $type = "";
	public $stack = array();
	private $attr = "";
	public $key = false;
	private $addField = "";

	/**
	 * Constructor for Scheme class.
	 * 
	 * @param string $table
	 * @throws SystemException if $table is empty
	 */
	public function  __construct($table = ""){
		if($table !=""){
			$this->table = $table;
		}else{
			throw new SystemException('You must specify a table name');
		}
		
	}

	/**
	 * Static method to instantiate Scheme object with a table name.
	 * 
	 * @param string $table
	 * @param string $dbsetting
	 * @return Scheme
	 */
	static function table($table, $dbsetting = 'default'){
		$t = new Scheme($table);
		$t->db = new DatabaseConnection($dbsetting);
		return $t;
	}

	/**
	 * Private method to create a table in the database.
	 * 
	 * @param string $table
	 * @return mixed
	 */
	private function _createTable($table){

		$sql='CREATE TABLE  `'.$this->db->mysql_escape_mimic($table).'` (';
		if($this->key == false){
			$sql .= '`id` INT NOT NULL AUTO_INCREMENT PRIMARY KEY';
		}else{
			$sql .= $this->stack[$table][$this->key];
		}
		$tmp = $this->stack[$table];

		if($this->key != false){
			unset($tmp[$this->key]);
		}
		foreach($tmp as $v){
			$sql .= ','.$v;
		}

		$sql .= ');';

		return $this->db->execute($sql);
	}

	/**
	 * Parses the SQL query to extract the table names.
	 * 
	 * @param string $sql
	 * @return array
	 */
	function SQLParseFrom($sql){

		preg_match('~(?:from|FROM)[ ]{1,}(?<FROM>[^^]+?)[ ]{1,}(?:JOIN|join|LEFT JOIN|left join|RIGHT JOIN|right join|WHERE|where|INNER JOIN|inner join)~', $sql, $matches, PREG_OFFSET_CAPTURE, 0);
		preg_match_all('/(?<table>\w+)[^,]+/', $matches['FROM'][0], $matches, PREG_SET_ORDER, 0);

		// Print the entire match result
		foreach($matches as $data){
			$tmp['table'][] = $data['table'];
		}

		return $tmp;
	}
	
	/**
	 * Private method to add a field to the table.
	 * 
	 * @param string $key
	 * @param string $opition
	 * @param string $aftername
	 */
	private function _addField($key, $opition, $aftername = ""){

		if($aftername != ""){
			$aftername = ' AFTER `'.$this->db->mysql_escape_mimic($aftername).'` ';
			$this->addField .= 'ADD `'.$this->db->mysql_escape_mimic($key).'` '.$opition.' '.$aftername;
		}
		else{
			$this->addField .= 'ADD `'.$this->db->mysql_escape_mimic($key).'` '.$opition;
		}
	}

	/**
	 * Private method to save added fields to the table.
	 * 
	 * @param string $table
	 */
	private function _saveToTable($table){
		if(substr($this->addField, -1) ==","){
			$this->addField = substr_replace($this->addField ,"",-1);
		}

		$this->db->execute('ALTER TABLE `'.$table.'` '.$this->addField);
		$this->addField = "";
	}

	/**
	 * Method to create tables based on provided function.
	 * 
	 * @param callable $function
	 * @return bool
	 */
	function create($function){
		
		$max = false;
		if($this->error != ""){
			$key = true;
			$i = 10;
			$function($this);			
			while($key){
				if(preg_match("/\.(.*)' doesn't /i", $this->error,$out)){
					if(isset($this->stack[$out[1]])){
						// make
						$this->_createTable($out);

					}else{
						//  do not
						$key = false;
						$max = false;
					}
				}

				if(preg_match("/Unknown column '(.*)' in 'field list'/i", $this->error,$out)){
					// get sql
					$tables = $this->SQLParseFrom($this->sql);
					//  check viken som saknas
					foreach($tables as $table){
						$data = $this->db->text->getRows('DESCRIBE %s',$table);
						$name = array();

						foreach($data as $k => $row){
							$name[$row['field']] = $row['field'];
						}
						$aftername = "";
						$fail = false;
						foreach($this->stack[$table] as $k => $v ){
							if(isset($name[$k]) != true){
								$this->_addField($k, $v, $aftername);
								$fail = true;
							}
							$aftername = $k;
						}
						
						if($fail){
							$this->_saveToTable($table);
							$fail = false;
							$max = true;
						}

						// run qeruy
						$this->db->getError = true;
						$this->db->execute($this->sql);

						if($this->db->getConnection()->error == ""){
							$key = false;
							$max = true;
						}
					}
				}
				$this->db->getError = true;
				$this->db->execute($this->sql);

				if($this->db->getConnection()->error == ""){
					$key = false;
					$max = true;
				}

				if($i == 10){
					$key = false;
					$max = true;
				}
			}
		}
		return $max;
	}

	/**
	 * Method to set attribute for a field.
	 * 
	 * @param string $attr
	 * @return $this
	 */
	function attr($attr){
		$this->attr = $attr;
		return $this;
	}

	/**
	 * Method to define an integer field.
	 * 
	 * @param string $max
	 * @return $this
	 */
	function int($max="m"){
		if($max == "s" || $max == "S"){
			$this->attr = "tinyint(1) NOT NULL";
		}
		if($max == "m" || $max == "M"){
			$this->attr = 'int(11) NOT NULL';
		}
		if($max == "l" || $max == "L"){
			$this->attr = 'bigint(20) NOT NULL';
		}
		return $this;
	}

	/**
	 * Method to define a float field.
	 * 
	 * @return $this
	 */
	function float(){
		$this->attr = "float DEFAULT NULL";
		return $this;
	}

	/**
	 * Method to define a double field.
	 * 
	 * @return $this
	 */
	function double(){
		$this->attr = "double DEFAULT NULL";
		return $this;
	}

	/**
	 * Method to define a decimal field.
	 * 
	 * @param int $x
	 * @param int $y
	 * @return $this
	 */
	function decimal($x=10,$y=5){
		$this->attr = sprintf("decimal(%d,%d) DEFAULT NULL",$x,$y);
		return $this;
	}

	/**
	 * Method to define a text field.
	 * 
	 * @param mixed $max
	 * @return $this
	 */
	function text($max){

		if(gettype($string) == "string" && strlen($max) <= 255){
			$this->attr = "varchar(".$max.") NOT NULL";
		}
		if($max == "s" || $max == "S"){
			$this->attr = "varchar(255) NOT NULL";
		}
		if($max == "m" || $max == "M" || strlen($max) > 255 && strlen($max) <= 65535){
			$this->attr = 'text NOT NULL';
		}
		if($max == "l" || $max == "L" || strlen($max) > 65535 && $strlen($max) <= 16777215){
			$this->attr = 'MEDIUMTEXT NOT NULL';
		}
		if($max == "xl" || $max == "XL" || strlen($max) > 16777215){
			$this->attr = 'LONGTEXT NOT NULL';
		}
		return $this;
	}

	/**
	 * Method to define an ENUM field.
	 * 
	 * @param array $values
	 * @return $this
	 */
	function list(array $values){
		$this->attr = "ENUM('".join("','", $values)."') NOT NULL";
		return $this;
	}

	/**
	 * Method to change the type of a field.
	 * 
	 * @param string $name
	 * @param string $value
	 * @return $this
	 */
	function ChangeTo($name, $value=""){
		if($value != ""){
			$this->db->execute('ALTER TABLE `%s` MODIFY `%s` `%s` %s',$this->table,$name,$name,$value);
		}
		return $this;
	}

	/**
	 * Method to add an updatedate field to the table.
	 * 
	 * @param string $name
	 * @return $this
	 */
	function updatedate($name){
		if($name){
			$this->updatedate = 'ADD COLUMN `'.$this->db->mysql_escape_mimic($name).'` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP';
		}
		return $this;
	}

	/**
	 * Method to define a field name and attribute.
	 * 
	 * @param string $name
	 * @return $this
	 */
	function name($name = ""){
		if($this->attr !="" && $name !=""){
			$this->stack[$this->table][$name]  = 'ADD COLUMN `'.$this->db->mysql_escape_mimic($name).'` '.$this->attr;
		}
	}

	/**
	 * Method to define a primary key field.
	 * 
	 * @param array $options
	 * @return $this
	 */
	function key($options = array()){
		$options = array_replace(array(
				'field' => '`id` ',
				'type' => ' INT',
				'negative' => '',
				'default' => ' NOT NULL',
				'auto' => ' AUTO_INCREMENT',
				'key' => ' PRIMARY KEY'
			), $options);

		
		$this->stack[$this->table][$options['field']] = implode($options);
		$this->key = $options['field'];
		return $this;
	}

	/**
	 * Method to set error and SQL for checking.
	 * 
	 * @param string $err
	 * @param string $sql
	 * @return $this
	 */
	function check($err="", $sql=""){
		$this->error = $err;
		$this->sql = $sql;
		return $this;
	}
}

	/* Scheme::check($err,$sql)->create(function($table){
		$table->table('table');
		$table->key();
		$table->text(155)->name('name');
		$table->text(150)->name('last');
		$table->text(150)->name('pass');

	});	 */

?>
