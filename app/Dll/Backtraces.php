<?
class backtraces extends record{
	function __construct($value = "backtraces_log"){
		parent::__construct($value);
	}
	function setEvents(array $set = array()){
		$d = array('date' => date("Y-m-d H:i:s"));
		$d = array_merge($d, $set);
		backtraces_log::$tmp = $d;
	}

	function __destruct(){
		foreach(backtraces_log::$tmp as $k => $v){
			$this->$k = $v;
		}
		$this->save();
	}
}
class backtraces_log{
	static $tmp;
}
?>