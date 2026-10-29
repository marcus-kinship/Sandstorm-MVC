<?
class Media{
    
    public $image="";  
    public $type="";  
    public $position="";  
    public $imageid="";
    public $id="";
    public $db;
    
    function __construct($imageid=null,$type=null){
        
	$this->imageid = $imageid;
	$arr = config::get('media types');
	$this->db = new DatabaseConnection();
	if($type==null){
	   $type = $arr[0];
	}
	if(array_search($type,$arr)!== FALSE){
	    $this->type = $type;
	}else{
	    throw new SystemException('Ogiltig regeluppsättningstyp "'.$type.'"');
	}
	
    }
        
    function save($array){

        $i=0;
        $sql = "INSERT INTO `media` (`id`, `images`, `position`, `imageid`, `type`)  VALUES ";
        foreach($array as $v){
            if($i==0){
            $sql.= "('null','".$v['adress']."','".$v['position']."','".$this->imageid.",'".$this->type."')";
            ++$i;
            }else{
            $sql.= ",('null','".$v['adress']."','".$v['position']."','".$this->imageid.",'".$this->type."')";
            }
        }
        $sql.=";";
       $this->db->insertRow($sql);
   
    }
    
    function get(){

        $img = $this->db->text->getRows("SELECT * FROM `media` WHERE `imageid` =%d ORDER BY `media`.`position` ASC ",$this->imageid);
        $obj = array();
        foreach($img as $k => $v){
            $data = new media($v['id']);
            $data->id = $v['id'];
            $data->image = $v['images'];
            $data->position = $v['position'];
            $data->imageid = $v['imageid'];
	    $data->type = $v['type'];
            $obj[] = $data; 
        }
        
        return $obj;
    }
    
    static function remove($pid,$id){
        $db = new DatabaseConnection();
        $data = $db->get()->Row('SELECT * FROM `media` WHERE id=%d and imageid=%d',$id, $pid);
        $getuser = User::getActiveUser();
        $path_parts = pathinfo($data['images']);
	$c = $_SERVER['DOCUMENT_ROOT'].'/upload/user/'.$getuser->username.'/'.$data->type.'s/'.$path_parts['filename'].'*';
	$matching = glob($c);
	foreach($matching as $h => $g){
    	    unlink($g);
	}	
        $db->delete("DELETE FROM `media` WHERE id=%d and imageid=%d",$id, $pid);
    }
    
    static function position($array){
        
    }
    
}
