<?php 
class Tagging{
    
    private $data = array();
    private $table;
    private $addField;
    
    function __construct($name="tags"){
        $this->table = $name;
    }
    
    function getTags(array $data){
	$db = new DatabaseConnection();
	$i=0;
	$tmp="";
	foreach($data as $id){
	    if($i==0){
		 $tmp.= "'".$db->mysql_escape_mimic($id)."'";
		 $i++;
	    }else{
		 $tmp.= ",'".$db->mysql_escape_mimic($id)."'";
	    }
	   
	}
	
	$data = $db->query("SELECT * FROM `".$this->table."` WHERE `words` IN($tmp);");
	return $data;
    }
    
    function getTagsByWords($words){
	$db = new DatabaseConnection();
	$i=0;
	$tmp="";
	foreach($words as $id){
	    if($i==0){
		 $tmp.= "'".$db->mysql_escape_mimic($id)."'";
		 $i++;
	    }else{
		 $tmp.= ",'".$db->mysql_escape_mimic($id)."'";
	    }
	   
	}
	$data = $db->query("SELECT * FROM `".$this->table."_save`, `".$this->table."` WHERE ".$this->table.".words IN($tmp) AND ".$this->table.".id = ".$this->table."_save.wordid");
	return $data;
    }
    
    function getTagsByObjectId($id){
	$db = new DatabaseConnection();
	$data = $db->getRows("SELECT * FROM `".$this->table."_save`, `".$this->table."` WHERE ".$this->table."_save.objectid = %d AND ".$this->table.".id = ".$this->table."_save.wordid",$id);
	return $data;
    }
        
    function getTagsByNumber($number){
	$db = new DatabaseConnection();
	
	$data = $db->getRows("SELECT * , COUNT(".$this->table."_save.wordid) AS cnt FROM `".$this->table."_save`, `".$this->table."` WHERE ".$this->table.".id = ".$this->table."_save.wordid GROUP BY ".$this->table."_save.wordid HAVING cnt > 1 ORDER BY cnt DESC  LIMIT 0,%d",$number);
	return $data;
    }

    function saveTags($word){
	$db = new DatabaseConnection();
	$data = $db->insertRow("INSERT INTO `".$this->table."` (`id`, `words`) VALUES (NULL, '%s');",$word);
	
	return $data;
    }
    
    function saveTagIdsObject($id,$objid){
	$db = new DatabaseConnection();
	$data = $db->insertRow("INSERT INTO `".$this->table."_save` (`id`, `wordid`, `objectid`) VALUES ('', '%d', '%d');",$id,$objid);
    
	return $data;
    }
    
    function removeTagIdsObject($eid,$objid){
	$db = new DatabaseConnection();
	$data = $db->delete("DELETE FROM `".$this->table."_save` WHERE `id` = %d AND `objectid` = %d",$eid,$objid);
		
	return $data;
    }
    
    function removeTag($word){
	$db = new DatabaseConnection();
	$data = $db->delete($eid,$userid);
	
	return $data;
    }
}