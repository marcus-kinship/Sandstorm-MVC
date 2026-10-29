<?
  class Table{
      public  $table = "";
      public  $cur = "";
      public  $data = "";

        function __construct($table,$options=array()){
          $this->table = $table;
          $this->data[$this->table]['options'] = $options;
        }

        public function __call($name, $arguments){
            $this->data[$this->table]['f'][$name]['data'] = $arguments[0];
            $this->data[$this->table]['f'][$name]['options'] = array();
            $this->cur = $name;
            return $this;
        }

        public function _defaultField(){

        }

        public function __destruct() {
          	$db = new DatabaseConnection();

                 foreach($this->data[$this->table]['f'][$name] as $field => $data){

                 }

            $step = $db->escapeString(array('CREATE TABLE  `%s` (%s) %s',$this->table,$data,$this->$this->data[$this->table]['options']));
            return $db->execute($step);   
        }

        public function __set($name,$value){
            if($name == 'options'){
                 $this->data[$this->table]['f'][$this->cur]['options'] = $value;
            }

        }
  }



$data = new table('data');
$data->data()->options = array();
