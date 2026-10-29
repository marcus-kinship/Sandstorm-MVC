<?
Class Querybuilder{

    private $table = "";
    private $data= array();
    private $row = array();

    function __construct($table="",array $data=array(), array $row=array()){
        $this->table = $table;
        $this->data = $data;
        $this->row = $row;
    }
    
    function multipleInserts($table="",$data=array()){
    	if($table==""){
          $table = $this->table;
          $data = $this->data;
        }
         $db = new DatabaseConnection();

        $sql ="INSERT INTO `".$table."` (`";
        if(array_key_exists('0', $data) && is_array($data['0'])){
		 $sql.= implode("`,`", array_keys($data['0']));
		 $sql.="`) VALUES";
		 
		 foreach($data as $rows){
		 	$sql.="(";
		 	$fi ="";
		 		foreach($rows as $v){
				$fi.="'".$db->mysql_escape_mimic($v)."',";
				}
		 	 $sql.= substr($fi, 0, -1);
		 	$sql.="),";
		 }
		 $sql= substr($sql, 0, -1);
		 
		 $sql.=';';
		 
		}else{
			$sql = false;
		}
        
	 return $sql;	
	}

    function insert($table="",$data=array()){
        if($table==""){
          $table = $this->table;
          $data = $this->data;
        }
        $set = array();
        $set[]="";
        $fs ="";
        $fi ="";
        $sql ="INSERT INTO `".$table."` (";
        foreach($data as $key => $value){
          
                $fs.='`'.$key.'`,';
    
                    $fi.="'%s',";
               
               $set[] = $value;
          
        }
        $sql.= substr($fs, 0, -1);
        $sql.=") VALUES (";
        $sql.= substr($fi, 0, -1);
        $sql.=")";

      $set[0] = $sql;
      return $set;
    }
    
     /**
  * Uppdaterar flera rad i databasen.
  *
  * <code> $obj->multipleUpdateSql('table',array(0 => array('name' => 'moa','year' => 25),1 => array('name' => 'mailn','year' => 28)),array('id'=>array(1,2))); </code>
  * @param en eller flera parametrar
  * @return string
  */
    
    function multipleUpdateSql($table,array $data = array(),array $where = array()){
        
         if(array_key_exists('0', $data) && is_array($data['0'])){
            
            $db = new DatabaseConnection();
            $str ="INSERT INTO `".$table."` (`";
            $keyid = array_key_first($where);
            $tmp[$keyid]="";
             
            $result = array_merge($tmp,$data['0']);
		    $str.= implode("`,`", array_keys($result));
		    $str.="`) VALUES";
		 
			foreach($data as $key => $rows){
		 	$str.="(";
		 	$fi ="";
		 		$fi.="'".$db->mysql_escape_mimic($where[$keyid][$key])."',";
		 		foreach($rows as $v){
				$fi.="'".$db->mysql_escape_mimic($v)."',";
				}
		 	 $str.= substr($fi, 0, -1);
		 	$str.="),";
		 }
		 $str= substr($str, 0, -1);

         $str.=" ON DUPLICATE KEY UPDATE";
         
        foreach(array_keys($data['0']) as $keys){
            $str.= "`".$keys."` = VALUES(`".$keys."`),";
        }
          
        $str= substr($str, 0, -1);
        $str.=";";
         }else{
			$str = false;
		}
    return $str;
    }

  /**
  * Uppdaterar flera rad i databasen.
  *
  * <code> $obj->multipleUpdates('table',array(0 =>array(1 => 'volvo',2 => 'kia')), array('id'=>array('in' => array(1,2)), 'and' => array('id =' =>5,etc ,array('case'=>'id'))); </code>
  * @param en eller flera parametrar
  * @return string
  */
    function multipleUpdates($table="",array $data = array(), array $where = array(), array $options = array() ){

      if($table==""){
          $table = $this->table;
          $data = $this->data;
        }

       if(!array_key_exists('case',$options)){
      
               $options['case'] = 'id';

       }


       $str =  "UPDATE `".$table."` ";
        $i=0;
         foreach($data as $key =>  $row){
            if($i==0){
             $str .= " SET ";
            }else{
             $str .= ", ";
            }
            $i++;

            $str .='`'.$key."` = CASE `".$options['case']."` ";
               foreach($row as $id => $v){

                $str .= " WHEN '".$id."' THEN '".$v."'";
               }
            $str .="   END";
         }
         $truein = false;
            $str .= " WHERE";
         foreach($where as $kv => $d){
          foreach($d as $key =>  $row){
               if($key=='in' or $key=='IN'){
         
                
                 $str .= " `".$kv."` IN(".join(',',$row).")";
              
                   $truein =  true;

               }

               if($key=='AND' or $key=='and'){ ;

                       foreach($row[$key] as $k =>  $v){
                           if($truein){
                               $str .=" AND `".$k."` = `".$v."`";

                           }else{
                               $truein = true;
                               $str .=" `".$k."` = `".$v."`";
                           }
                        }
                    }

                if($key=='OR' or $key=='or'){ ;

                   foreach($row[$key] as $k =>  $v){
                       if($truein){
                           $str .=" OR `".$k."` = `".$v."`";

                       }else{
                           $truein = true;
                           $str .=" `".$k."` = `".$v."`";
                       }
                    }
                }
             }
          }


     return $str;
    }

    function update($table="",array $data=array(),array $row=array()){
        if($table==""){
          $table = $this->table;
          $data = $this->data;
          $row = $this->row;
        }

        $set = array();
        $set[]="";
        $fs ="";
        $fi ="";
        $i=0;
        $sql ="UPDATE `".$table."` SET ";
        foreach($data as $key => $value){
      
             
                    $fs.='`'.$key."` = '%s',";
               
               $set[] = $value;
          
        }
        $sql.= substr($fs, 0, -1);
        $sql.=") WHERE ";
        foreach($row as  $row){
          if(is_numeric($row)){
               $fi.="`".$row."` = '%d'";
                 }else{
               $fi.="`".$row."` = '%s'";
          }
          if($i==0){
              ++$i;
          }else{
            $fi.=" AND ";
          }
        }
        $sql.= substr($fi, 0, -1);
        $set[] = $row;
        $set[0] = $sql;
        return $set;
    }
}