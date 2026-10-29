<?
class comment{
    
    public $tagetid;
    public $userid;
    public $makeid;
    public $posts;
    private $db;
    
    function __construct(){
		$this->db = new DatabaseConnection();
	}
        
    function notAsReadByTaget($tagetid){

	$data = $this->db->text->getRows('SELECT * FROM `comment` WHERE tagetid = %d AND read = 1', $tagetid);
	return $data;
    }
    function asReadByUser($tagetid){
	$tmp = array();
	$user = User::getActiveUser();
	$data =  $this->db->text->getRows("SELECT id FROM  `comment` WHERE  `tagetid` = %d AND `read`= 1",$tagetid);
	if(!empty($data)){
	foreach($data as $data){
	   $tmp[] = $data['id'];
	}
	$this->db->updateRow("UPDATE  `comment` SET  `read` = 0 WHERE id IN(".implode(",", $tmp).")");
	}
    }
    
    function getNumberOfTaget($tagetid){             

         $data = $this->db->text->getSingleRow('SELECT COUNT(*) FROM `comment` WHERE tagetid = %d', $tagetid);
         $data = $data['COUNT(*)'];
         return $data;   
    }
    
    function getByTagetidAndUserid($tagetid){
	$user = User::getActiveUser();

	$data = $this->db->text->getRows('SELECT users.*, comment.* FROM users, comment WHERE comment.tagetid = %d AND comment.userid= %d AND comment.userid=users.id', $tagetid,$user->id);
	return $data;
    }
    
    function getByTagetId($tagetid,$limit=""){
	$data = $this->db->text->getRows('SELECT users.*, comment.* FROM users, comment WHERE tagetid = %d AND comment.userid=users.id '.$limit, $tagetid);
	return $data;
    }
    
    function getByUserid($tagetid){
	$user = User::getActiveUser();
	
	$data = $this->db->text->getRows('SELECT users.*, comment.* FROM users, comment WHERE userid = %d AND comment.userid=users.id',$user->id);
	return $data;
    }
    
    function getByMakeid(){
	$user = User::getActiveUser();

	$data = $this->db->text->getRows('SELECT users.*, comment.* FROM users, comment WHERE makeid= %d AND comment.userid=users.id',$user->id);
	return $data;
     
    }
    
    function getByIdAndUserid($id){

	$user = User::getActiveUser();
	$data = $this->db->text->getSingleRow('SELECT users.*, comment.* FROM users, comment WHERE id = %d AND makeid= %d AND comment.userid=users.id', $id,$user->id);
         return $data;
    }
    
    function remove($id){
	$user = User::getActiveUser();

	$data = $this->db->text->getSingleRow('SELECT * FROM `comment` WHERE id = %d', $id);
	if($user->id == $data['makeid'] || $user->id == $data['userid']){
	   return $db->delete("DELETE FROM `comment` WHERE `id`= %d",$id);
	}
	
    }
    
    function clearCommentsByTagetid($tagetid){
	$user = User::getActiveUser();
	return $this->db->delete("DELETE FROM `comment` WHERE `makeid` = %d AND tagetid = %d ",$user->id, $tagetid);
    }
    
    function save(){

	    if(!isset($this->tagetid)){
		   return _("Ingen tagetid");
	    }
	    
	    if(!isset($this->makeid)){
		   return _("Ingen makeid som tillhör tagetid");
	    }
	    
	    if(!isset($this->userid)){
		   return _("Ingen userid");
	    }
	    
	    if(!isset($this->posts)){
		   return _("Ingen posts data");
	    }
	    

	$user = User::getActiveUser();
	$read = 1;

	$id =  $this->db->insertRow("INSERT INTO `comment` (`id`, `userid`, `date`, `tagetid`, `read`, `makeid`, `posts`) VALUES (NULL, '%d', CURRENT_TIMESTAMP, '%d', '%d', '%d','%s');",$this->userid, $this->tagetid, $read, $this->makeid, htmlentities($this->posts));
	return 	$id;
	
    }
    
}