<?
class Flatdata{

    public $data = array();
    public $_data = array();
    public $_type = false;
    private $tmp = array();
    private $updatedate;
    public $db;
    public $table;
    private $options;
    private $totaly;
    private $search = false;
    private $sort = array();
    private $sortP = "";
    private $status = array();
    private $statusP = "";
    private $define = array();
    private $defineP = "";
    private $_datatype = 1;

    /**
     * Hantare en tabell i databasen.
     *
     * <code> $obj = new Flatdata('table'); </code>
     * @param $namne
     * @param $options
     * @return false eller true
     */

    function __construct($name, $options = array()){
        $this->table = $name;
        $this->options = $options;
        $this->db = new DatabaseConnection();
    }

    private function _createTable($table){

        $sql = "CREATE TABLE `$table` (
            `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
            `gruopid` bigint(20) NOT NULL,
            `attr` varchar(255) DEFAULT NULL,
            `data` longtext DEFAULT NULL,
            `status` TINYINT(4) NULL DEFAULT '1',
            `sort` INT  UNSIGNED NOT NULL,
            `time` TIMESTAMP on update CURRENT_TIMESTAMP NOT NULL
          ) ENGINE=MyISAM DEFAULT CHARSET=utf8;";

        $this->db->query($sql);

        $sql = "ALTER TABLE `$table` ADD FULLTEXT KEY `data` (`data`);";

        $this->db->query($sql);
    }

    private function _checkMysqlTable($fun = ""){
        $check = false;

        if (!empty($this->db->error)) {

            if (preg_match("/\.(.*)' doesn't /i", $this->db->error, $out)) {
                $this->_createTable($out[1]);
                return true;
            }
        }
    }

    /**
     * Ta bort en tabell eller ta bort flera objekt från en tabell i databasen.
     *
     * <code> $obj = new Flatdata('table'); $obj->drop(); or  $obj->drop('gruopid','gruopid'); </code>
     * @param $gruopid
     * @return false eller true
     */

    function drop($gruopid = ""){

        if ($gruopid) {
            $args = func_get_args();
            return $this->db->delete("DELETE FROM `%s` WHERE `%s`.`gruopid` in (%s)", $this->table, $this->table, implode("','", $args));
        } else {
            return  $this->db->delete('DROP TABLE IF EXISTS `%s`;', $this->table);
        }
    }

    /**
     * Ta bort ett fält från ett objekt eller ta bort ett fält från flera objekt från en tabell i databasen.
     *
     * <code> $obj = new Flatdata('table'); $obj->deleteObj('gruopid'); $obj->deleteObj([1,2]); </code>
     * @param mix $gruopid 
     * @return false eller true
     */

    function deleteObj($gruopid = ""){

        if ($gruopid != "") {
            if(!is_array($gruopid)){
                return $this->db->delete("DELETE FROM `%s` WHERE `gruopid` = %d", $this->table, $gruopid);
            }else{
                return $this->db->delete("DELETE FROM `%s` WHERE `gruopid` IN(%s)", $this->table, implode(",", $gruopid));
            }
           
        } else {
            return false;
        }
    }


    /**
     * Ta bort ett fält från ett objekt eller ta bort ett fält från flera objekt från en tabell i databasen.
     *
     * <code> $obj = new Flatdata('table'); $obj->deleteAttr('gruopid','attr'); </code>
     * @param string $attr
     * @param int $gruopid
     * @return false eller true
     */

    function deleteAttr($attr = "", $gruopid = ""){

        if ($gruopid !="") {
            return $this->db->delete("DELETE FROM `%s` WHERE `gruopid` = %d AND `attr` = '%s' ", $this->table, $gruopid, $attr);
        } else {
            return $this->db->delete("DELETE FROM `%s` WHERE  `attr` = '%s' ", $this->table, $attr);
        }
    }

    /**
     * Funktion som sätt define data för söka smartare
     * 
     * <code> 
     * $obj = new Flatdata('table');  
     * $obj->define('car')->key = "data";
     * $obj->define('boat')->key2 = "data2";
     * 
     * $obj->save();
     * </code>
     * 
     * @param int $number
     * @return $this
     */

    function define(string $data){
        $this->defineP = $data; 
        return $this;
    }

    /**
     * Funktion som sätt status på fält med sätta data för lättare skiva ut data
     * 
     * <code> 
     * $obj = new Flatdata('table');  
     * $obj->status(1)->key = "data";
     * $obj->status(2)->key2 = "data2";
     * 
     * $obj->save();
     * </code>
     * 
     * @param int $number
     * @return $this
     */

    function status(int $number){
        $this->statusP = $number; 
        return $this;
    }

    /**
     * Funktion som sätt ordning på fält med sätta data för lättare skiva ut data
     * 
     * <code> 
     * $obj = new Flatdata('table');  
     * $obj->sort(1)->key = "data";
     * $obj->sort(2)->key2 = "data2";
     * 
     * $obj->save();
     * </code>
     * 
     * @param int $number
     * @return $this
     */

    function sort(int $number){
        $this->sortP = $number; 
        return $this;
    }

    /**
     * Funktion som sätt ordning eller status på fält utan sätta data för lättare skiva ut data
     * 
     * <code> 
     * $obj = new Flatdata('table');  
     * $obj->sort(1)->key('key');
     * $obj->status(4)->sort(2)->key('key2');
     * 
     * $obj->save();
     * </code>
     * 
     * @param int $key
     * @return $this
     */

    function key($name){

        foreach(['sort','define','status'] as $v){
            if($this->{$v.'P'} !=""){
                $this->{$v}[$name] = $this->{$v.'P'}; 
                $this->{$v.'P'} = "";
            }
        }

        return $this;
    }

    function __set($name, $value){

        foreach(['sort','define','status'] as $v){
            if($this->{$v.'P'} !=""){
                $this->{$v}[$name] = $this->{$v.'P'}; 
                $this->{$v.'P'} = "";
            }
        }
     
        $this->data[$name] = $value;

    }

    /**
	* Hämtar data och ange nyckar som text, nummer eller text och nummer.
	*
	* <code>
    * $db = new Flatdata('table');  
	* $db->text->function('frågan %d ,%s','data','data','etc');
	* $db->numeric->function('frågan %d ,%s','data','data','etc');
	* $db->both->function('frågan %d ,%s','data','data','etc');
	* </code>
	* @param en eller flera parametrar
	* @return mix
	*/

    function __get($name){

        if($name == "text"){
            $this->_datatype = 0;
            return $this;
        }

        if($name == "numeric"){
            $this->_datatype = 1;
            return $this;
        }

        if($name == "both"){
            $this->_datatype = 2;
            return $this;
        }

        if(key_exists($name, $this->data)) {
            return $this->data[$name];
        }else{
            return false;
        }
    }

    /**
     * Funktion ger totalt värdet av en sökning 
     * 
     * <code> 
     * $obj = new Flatdata('table');  
     * $data = $obj->search($word);
     * $obj->getTotaly();
     * </code>
     * @return number or false om  inte search funktionen har anropat innnan då få man en throw
     */

    function getTotally(){

        if($this->search){
             $data = $this->db->getSingleRow($this->totaly);
            return $data['cnt'];
        }else{
            throw new SystemException(_('Du måste göra en sökning genom att du anropar search funktionen innan du kan få totala värdet av sökning'));
        }

    }

    /**
     * Funktion hämta från databasen av sök  i data
     * 
     * <code> 
     * $obj = new Flatdata('table');  
     * $data = $obj->search($word);
     * </code>
     * @param array $gruopid
     * @return array or false
     */

    function search(string $word="", array $options = array()){

        if($word==""){
            return false;
        }

        $this->search = true;

        $def = array(
            'word' => '*%s*',
            'order by' => " `gruopid` ASC, `sort` ASC ",
            'limit' => '',
            'status' => '= 1',
            'keys' =>  array(
                'order by' => " `gruopid` ASC, `sort` ASC ",
                'status' => '= 1'
            )
        );
        if (isset($options)) {
            $options = array_merge($def, $options);
        } else {
            $options = $def;
        }

        $cnt = substr_count($word," ");
  
        if($cnt >0){
            if(preg_match('/^"(\S{0,})"$/', $word, $m)){
	        	$options['word'] = "%s";
	        	$word = $m[1];
	        }else{
                $options['word'] = "%s";
            }
		
        }else{
	
            if(preg_match('/^"(\S{0,})"$/', $word, $m)){
                $options['word'] = "%s";
                $word = $m[1];
            }

            
        }
        
        $word = $this->db->real_escape_string(sprintf($options['word'],$word));

        if (is_array($options['status'])) {
            $options['status'] = "AND status ".implode(" OR status ", $options['status']);
        }else{
            $options['status'] = "AND status ". $options['status'];
        }
    
        if ($options['limit'] != "") {
            $options['limit'] = 'LIMIT ' . $options['limit'];
        }

        if ($options['order by'] != "") {
            $options['order by'] = 'ORDER BY ' . $options['order by'];
        }
        
        if ($options['keys']['order by'] != "") {
            $options['keys']['order by'] = 'ORDER BY ' . $options['keys']['order by'];
        }

        if (is_array($options['keys']['status'])) {
            $options['keys']['status'] = "AND status ".implode(" OR status ", $options['keys']['status']);
        }else{
            $options['keys']['status'] = "AND status ". $options['keys']['status'];
        }
        
        $subquery = "SELECT DISTINCT  `gruopid` FROM `". $this->table . "` WHERE MATCH(data) AGAINST ('" . $word . "' IN BOOLEAN MODE) ".$options['status']." ". $options['order by'] . $options['limit'];


        $data = $this->db->text->getRows("SELECT * FROM  `". $this->table . "`  WHERE `gruopid` IN(".$subquery.") ".$options['keys']['status'].' '.$options['keys']['order by']);
        print $this->db->getSQL();
     
        $this->totaly = 'SELECT COUNT(DISTINCT `gruopid`) AS cnt FROM `' . $this->table . '` WHERE MATCH(data) AGAINST (\'' . $word . '\' IN BOOLEAN MODE) '.$options['status'];
	
		 if ($data) {
            $tmp = array();
            if($this->_datatype == 0){

                foreach ($data as $c => $vx) {

                    $tmp[$vx['gruopid']]['id'] = $vx['gruopid'];
                    $tmp[$vx['gruopid']][$vx['attr']] = $vx['data'];
                }
            }

            if($this->_datatype == 1){
               
                foreach ($data as $c => $vx) {
             
                    $tmp[$vx['gruopid']][0] = $vx['gruopid'];
                    $tmp[$vx['gruopid']][] = $vx['attr'];
                    $tmp[$vx['gruopid']][] = $vx['data']; 
                } 
            }

            if($this->_datatype == 2){
               
                foreach ($data as $c => $vx) {
             
                    $tmp[$vx['gruopid']][0] = $vx['gruopid'];
                    $tmp[$vx['gruopid']]['id'] = $vx['gruopid'];
                    $tmp[$vx['gruopid']][] = $vx['attr'];
                    $tmp[$vx['gruopid']][$vx['attr']] = $vx['data'];
                    $tmp[$vx['gruopid']][] = $vx['data']; 
                } 
            }

            return $tmp;
        } else {
            return false;
        }

    }

    /**
     * Funktion hämta från databasen av flera gruopid
     * 
     * <code> 
     * $obj = new Flatdata('table');  
     * $data = $obj->getByIds(array(1,2,3));
     * </code>
     * @param array $gruopid
     * @return array or false
     */

    function getByIds($gruopid,$options = array()){

        if(empty($gruopid)){
            return false;
        }

        $def = array(
            'order by' => " `gruopid` ASC, `sort` ASC ",
            'status' => '= 1'
        );
        if (isset($options)) {
            $options = array_merge($def, $options);
        } else {
            $options = $def;
        }

        if ($options['order by'] != "") {
            $options['order by'] = 'ORDER BY ' . $options['order by'];
        }

        if (is_array($options['status'])) {
            $options['status'] = "AND status ".implode(" OR status ", $options['status']);
        }else{
            $options['status'] = "AND status ". $options['status'];
        }

        $data = $this->db->text->getRows('SELECT * FROM `' . $this->table . '` WHERE `gruopid` IN(%s) '.$options['status'].' ' . $options['order by'].' ', implode(",", $gruopid));

        if ($data) {
            $tmp = array();
            if($this->_datatype == 0){

                foreach ($data as $c => $vx) {

                    $tmp[$vx['gruopid']]['id'] = $vx['gruopid'];
                    $tmp[$vx['gruopid']][$vx['attr']] = $vx['data'];
                }
            }

            if($this->_datatype == 1){
               
                foreach ($data as $c => $vx) {
             
                    $tmp[$vx['gruopid']][0] = $vx['gruopid'];
                    $tmp[$vx['gruopid']][] = $vx['attr'];
                    $tmp[$vx['gruopid']][] = $vx['data']; 
                } 
            }

            if($this->_datatype == 2){
               
                foreach ($data as $c => $vx) {
             
                    $tmp[$vx['gruopid']][0] = $vx['gruopid'];
                    $tmp[$vx['gruopid']]['id'] = $vx['gruopid'];
                    $tmp[$vx['gruopid']][] = $vx['attr'];
                    $tmp[$vx['gruopid']][$vx['attr']] = $vx['data'];
                    $tmp[$vx['gruopid']][] = $vx['data']; 
                } 
            }

            return $tmp;
        } else {
            return false;
        }
    }

    /**
     * Funktion hämta från databasen av gruopid
     * 
     * <code> 
     * $obj = new Flatdata('table');  
     * $data = $obj->getById($gruopid);
     * </code>
     * @param int $gruopid
     * @return array or false
     */

    function getById($gruopid ="",$options = array()){

        if($gruopid == ""){
            return false;
        }

        $def = array(
            'order by' => " `sort` ASC ",
            'status' => '= 1'
        );

        if (isset($options)) {
            $options = array_merge($def, $options);
        } else {
            $options = $def;
        }

        if ($options['order by'] != "") {
            $options['order by'] = 'ORDER BY ' . $options['order by'];
        }

        if (is_array($options['status'])) {
            $options['status'] = "AND status ".implode(" OR status ", $options['status']);
        }else{
            $options['status'] = "AND status ". $options['status'];
        }

        $data = $this->db->text->getRows('SELECT * FROM `' . $this->table . '` WHERE `gruopid` = %d '.$options['status'].' ' . $options['order by'], $gruopid);

        if ($data) {
            $tmp = array();

            if($this->_datatype == 0){
                
                foreach ($data as $c => $vx) {
               
                    $tmp['id'] = $vx['gruopid'];
                    $tmp[$vx['attr']] = $vx['data'];
                    
                }
            }
            
            $tmp[] =  $gruopid;

            if($this->_datatype == 1){
                
                foreach ($data as $c => $vx) {

                    $tmp[] = $vx['attr'];
                    $tmp[] = $vx['data'];
                }
            }

            if($this->_datatype == 2){
                
                foreach ($data as $c => $vx) {

                    $tmp['id'] = $vx['gruopid'];
                    $tmp[] = $vx['attr'];
                    $tmp[$vx['attr']] = $vx['data'];
                    $tmp[] = $vx['data'];
                    
                }
            }

            return $tmp;
        } else {
            return false;
        }
    }
    
    /**
     * Funktion som sätter status på ett objekt eller på nyckel i tabell i databasen
     * 
     *
     * <code> 
     * $obj = new Flatdata('table'); 
     * $obj->setStatus([id => status, 2 => 2]);  
     * </code>
     * @param array
     * @return true or false or throw if empty array
     */

    function setStatus(array $status){

        $set = array();
        $set[] = "";
        
        if(empty($status)!=true){

            $sql = "UPDATE `" . $this->table . "`";
            $sql .= "  SET status = CASE id";
            foreach ($status as $key => $value) {
                $sql .= " WHEN %d THEN '%s' ";
                $set[] = $key;
                $set[] = $value;
            }

            $sql .= " ELSE data END,";
            $sql .= " time = NOW() WHERE `id` IN(".implode(",",array_keys($status)).")";
            $set[0] = $sql;

            return $this->db->updateRow($set);

        }else{
            throw new SystemException(_('Du måste skicka in en array med nycklar som id och data som nummer på status, Du har anget en tom array'));
        }
    }

    function repleceAll($key,$data){

        $update = $this->db->updateRow('UPDATE `'.$this->table.'` SET data = \'%s\', time = NOW() WHERE `attr` = \'%s\'', $key,$data);
    }

    // UPDATE test2 SET data = CASE id WHEN 11 THEN 'data' WHEN 12 THEN 'data2' ELSE data END WHERE id IN (11, 12)

    function update($table, $data = array(), $sort = array(),$status = array(),$define = array()){

        $set = array();
        $set[] = "";
        $sql = "UPDATE `" . $table . "`";

        if(empty($data)!=true){
            $sql .= "  SET data = CASE id";
            foreach ($data as $key => $value) {
                $sql .= " WHEN %d THEN '%s' ";
                $set[] = $key;
                $set[] = $value;
            }

            $sql .= " ELSE data END,";
        }

        if(empty($sort)!=true){

            if(empty($data)==true){
                $sql .= "  SET";
            }

            $sql .= " sort = CASE id";

            foreach ($sort as $key => $value) {
                $sql .= " WHEN %d THEN %d ";
                $set[] = $key;
                $set[] = $value;
            }
            
            $sql .= " ELSE sort END,";
        }


        if(empty($status)!=true){

            if(empty($data)==true){
                $sql .= "  SET";
            }else{
                if(empty($sort)==true){
                    $sql .= "  SET";
                }
            }

            $sql .= " status = CASE id";

            foreach ($status as $key => $value) {
                $sql .= " WHEN %d THEN %d ";
                $set[] = $key;
                $set[] = $value;
            }
            
            $sql .= " ELSE status END,";
        }

        if(empty($define)!=true){

            if(empty($data)==true){
                $sql .= "  SET";
            }else{
                if(empty($sort)==true){
                    $sql .= "  SET";
                }else{
                    if(empty($sort)==true){
                        $sql .= "  SET";
                    }
                }
            }

            $sql .= " define = CASE id";
           
            foreach ($define as $key => $value) {
                $sql .= " WHEN %d THEN '%s' ";
                $set[] = $key;
                $set[] = $value;
            }
            
            $sql .= " ELSE define END,";
        }

        $sql .= " time = NOW() WHERE `id` IN(".implode(",",array_keys($data)).")";
    
        $set[0] = $sql;

        return  $set;
    }

    //  INSERT into `text` (gruopid,attr,data,status,time)  
    // Values 
    // (2,'aa',9,1,NOW()),
    // (2,'bb',15,1,NOW())

    function insert($table, $gruopid, $sort, $data, $status,$define){

        $set = array();
        $set[] = "";
        $fs = "";
        $fi = "";
        $s = $sort;
        $gruopid++;

        $sql = "INSERT INTO `" . $table . "` (`id`, `gruopid`,`attr`,`data`,`define`,`status`,`sort`,`time`) Values";
        foreach ($data as $key => $value) {

            $fs .= "(NULL,%d,'%s','%s','%s',%d,%d,NOW()),";
            $set[] = $gruopid;
            $set[] = $key;
            $set[] = $value;

            if($define[$key]!=""){
                $set[] = $define[$key];
            }else{
                $set[] = gettype($value);
            }

            if($status[$key]!=""){
                $set[] = $status[$key];
            }else{
                $set[] = 1;
            }

            $set[] = $s;
            $s++;
        }

        $sql .= substr($fs, 0, -1);
        $sql .= ";";

        $set[0] = $sql;
        return $set;
    }

    /**
     * Funktion spara ett objekt till tabell i databasen
     * samt uppdatera ett objekt till tabell i databasen.
     *
     * <code> 
     * $obj = new Flatdata('table'); 
     * $obj->key = "data";  
     * $obj->key2 = "data2";  
     * $obj->save(); //skick med gruopid för uppdatera 
     * </code>
     * @param int or array $FROM table ORDER BY id DESC LIMIT 1;
     * @return last id
     */

    function save($gruopid = ""){

        if ($gruopid) {

            $c = $this->data;

            $update = $this->db->getRows("SELECT id,`gruopid`,`attr`,`sort` FROM `%s` WHERE `gruopid` = %d AND `attr` in('".implode("','",array_keys($c))."')", $this->table,$gruopid);
   
            if ($update == false) {
                
                $c = $this->data;
                $sort = $this->db->text->getSingleRow("SELECT sort FROM %s where gruopid = %d ORDER BY sort DESC LIMIT 1; ", $this->table,$gruopid);
                $s = $this->insert($this->table, $gruopid,$sort['sort'], $c,$this->status,$this->define);
                
                $update = $this->db->insertRow($s);

            }else{

                $status = array();
                $sortera = array();
                $input = array();
                $define = array();
                
                foreach($update as $rows){
                    if($this->status[$rows['attr']]!=""){
                         $status[$rows['id']] = $this->status[$rows['attr']];
                    }

                    if($this->sort[$rows['attr']]!=""){
                        $sortera[$rows['id']] = $this->sort[$rows['attr']];
                    }

                    if($this->define[$rows['attr']]!=""){
                        $define[$rows['id']] = $this->define[$rows['attr']];
                    }

                    $input[$rows['id']] = $c[$rows['attr']];
                }
 
                $s = $this->update($this->table,$input,$sortera,$status,$define);
             
                $update = $this->db->updateRow($s);
              print $this->db->getSQL();
            }

            return $update;
        }

        $d = $this;
        $sort = 0;
        $data = $this->db->notSetTable(function () use ($d) {

            $sql = "CREATE TABLE `$d->table` (
                `id` bigint(20) NOT NULL AUTO_INCREMENT PRIMARY KEY,
                `gruopid` bigint(20) NOT NULL,
                `attr` varchar(255) DEFAULT NULL,
                `data` longtext DEFAULT NULL,
                `define` varchar(255) DEFAULT NULL,
                `status` TINYINT(4) NULL DEFAULT '1',
                `sort` INT  UNSIGNED NOT NULL,
                `time` TIMESTAMP on update CURRENT_TIMESTAMP NOT NULL
              ) ENGINE=MyISAM DEFAULT CHARSET=utf8;";

            $d->db->query($sql);

            $sql = "ALTER TABLE `$d->table` ADD FULLTEXT KEY `data` (`data`);";

            $d->db->query($sql);
        })->text->getSingleRow("SELECT gruopid,sort FROM %s ORDER BY gruopid DESC,sort DESC LIMIT 1; ", $this->table);

        if (is_array($data)) {
            $gruopid = $data['gruopid'];
        }

        if ($gruopid == "") {
            $gruopid = 0;
            $sort = 0;
        }

        $c = $this->data;
        $s = $this->insert($this->table, $gruopid, $sort, $c,$this->status,$this->define);
        $id = $gruopid + 1;
        $this->db->insertRow($s);

        return $id;
    }

    function getTable(){
        return $this->table;
    }
}
