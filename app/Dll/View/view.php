<?php 
class view extends element{
	public $optionsform = array();
	public $store;
	
	function __construct(array $options=array(),store $obj){
		
	$tmp = array(
            'style' => false,
            'id' => false,
            'class' => false,
            'action' => "",
            'method' => 'post',
	    'message' => false,
            'name' => false,
            'accept' => false,
            'target' => false,
            'accept-charset'=>false,
            'autocomplete'=> 'on',
            'mode' => 'html',
	    'formtype' => 'save',
	    'title' => null,
            'pagination' => 2,
	    'stringnavigation' => 'page',
	    'searchstring' => 'q',
	    'order by' => array('name'=>'timestamp','method' => 'DESC')
        );
	
         $option = $tmp;
         foreach($options as $key => $value) {
            if(array_key_exists($key, $tmp)) {
                 $option[$key]=$value;
            }else{
                throw new SystemException('Argumentationsfel: (%s) finns inte i som inställning ',$key.' => '.$value);
            }
         }
	$obj->optionsform = $option;
        $this->store = $obj;	
		
	 return $this;	
	}
	
	function updateSingleform($ids){
		
		$opt = $this->optionGenerator($this->store->optionsform);
		if($this->store->optionsform['mode']=="html"){
		$forms = "";	
		}
		
		  $db = new DatabaseConnection();
		  
		
		$data = new Validation($this->store->name);
		
		foreach($this->store->elm as $tables => $table){
			$datarow = $db->text->get()->Row('SELECT * FROM  `%s` WHERE  `id` =%d ',$tables,$ids);
			
			$this->tabletree[$tables]['id']=null;
			$this->tabletree[$tables]['timestamp'] = date("Y-m-d H:i:s");
			foreach($table as $elements => $element){
			
				foreach($element as $options => $option){
				
				
					if(get_class($option)!='getMessage' || $option->options['message']!=false || $option->options['message']!=false){
				
					$data->setinput($option->name,$option->options['message'], $option->options['validation']);
			
					if($option->options['htmlform']!=false){
						$this->store->tabletree[$tables][$option->name] = request::req($option->name);
					
					}else{
			
						$this->store->tabletree[$tables][$option->name] = $option->options['value'];
					}
					if(isset($datarow[$option->name])){
					$option->data = $datarow[$option->name];
					}
					$this->store->tabletreeoptions[$tables][$option->name]=$option->options['tableoptions'];
					$this->store->tabletreeoptions[$tables]['timestamp']="datetime NOT NULL ";
					}else if(get_class($option)=='getMessage'){
					$this->store->passivmessage = true;
					}
					
				}
			}
		}

		
		if($data->valid()=='true'){
		
		foreach($this->store->tabletree as $k => $v){
				$user = new Record($k);
				foreach($v as $name => $value){
				$user->{$name} = $value;	
				}
			
				$user->save($ids);
				 unset($user);
				}	

			
	 
			
			
		session::set('done',true);
	
		SiteRouter::wayto2(SiteRouter::$url);
		
		}
		
		foreach($this->store->elm as $tables => $table){
			foreach($table as $elements => $element){
				foreach($element as $options => $option){
					if(get_class($option)=='getMessage' && $data->formisvalid=='false'){
					$e = $data->geterrors();
					$forms.= $option->render($e['message'])."\n";
					}else{
					$forms.= $option->render()."\n";	
					}
				}
			}
		}
		
		$form = '<form '.$opt.'>'."\n";

		if($this->store->passivmessage==false && $data->formisvalid=='false'){
			$e = $data->geterrors();
			$form.= '<div class="error">'.$e['message'].'</div>'."\n";
		}
		
		
		
		if( session::get('done')==true && $this->store->optionsform['message']!=false){
		  $form.= '<div class="error">'.$this->store->optionsform['message'].'</div>'."\n";
		session::remove('done');
		}
		
		$form.= $forms;
		$form.= $this->store->formhtml[$this->store->name];
		$form.= '</form>'."\n";
		
		return $form;
		
	}
	
	function makeform(){
		
		$opt = $this->optionGenerator($this->store->optionsform);
		if($this->store->optionsform['mode']=="html"){
		$forms = "";	
		}
		
		
		$data = $this->store->makeTableTree();
		
	
		if($data->valid()=='true'){

			$db = new DatabaseConnection();
			$db->getError = true;
			$sql = new Querybuilder();
		
			foreach($this->store->tabletree as $k => $v){
				
			    $db->insertRow($sql->insert($k,$v));
			}
			
			$err = $db->getErrorMessage();
		
	
			$db->getError = false;
			
			if($this->store->_checkMysqlTable($err)){
			
				foreach($this->store->tabletree as $k => $v){   
				$db->insertRow($sql->insert($k,$v));
				}
			}
			
		session::set('done',true);
		SiteRouter::wayto2(SiteRouter::$url);
		
		}
		
		foreach($this->store->elm as $tables => $table){
			foreach($table as $elements => $element){
				foreach($element as $options => $option){
					if(get_class($option)=='getMessage' && $data->formisvalid=='false'){
					$e = $data->geterrors();
					$forms.= $option->render($e['message'])."\n";
					}else{
					$forms.= $option->render()."\n";	
					}
				}
			}
		}
		
		$form = '<form '.$opt.'>'."\n";

		if($this->store->passivmessage==false && $data->formisvalid=='false'){
			$e = $data->geterrors();
			$form.= '<div class="error">'.$e['message'].'</div>'."\n";
		}
		
		
		
		if( session::get('done')==true && $this->store->optionsform['message']!=false){
		  $form.= '<div class="error">'.$this->store->optionsform['message'].'</div>'."\n";
		session::remove('done');
		}
		
		$form.= $forms;
		$form.= $this->store->formhtml[$this->store->name];
		$form.= '</form>'."\n";
		
		return $form;
		
	}
	
	function getFormData(){
		$this->store->makeTableTree();
		
		$db = new DatabaseConnection();
		$db->getError = true;
		$word = $db->mysql_escape_mimic(trim(request::req($this->store->optionsform['searchstring'])));
		
		
		$form = "";
		$p = new Pagination($this->store->optionsform['pagination']);
		$p->currentPage(request::req($this->store->optionsform['stringnavigation']));
	
		if($word){     
		     $p->num_rows($p->setLimitQuery($this->store->_findSql($word,$p->limit()),$db));
		     $data = $db->text->query($this->store->_findSql($word,' ORDER BY  `'.$this->store->optionsform['order by']['name'].'` '.$this->store->optionsform['order by']['method'].' '.$p->limit()));
		  }else{
		 $p->num_rows($p->setLimitQuery($this->store->_selectSql($p->limit()),$db));
			$data = $db->text->query($this->store->_selectSql(' ORDER BY  `'.$this->store->optionsform['order by']['name'].'` '.$this->store->optionsform['order by']['method'].' '.$p->limit()));
		}
		
		$err = $db->getErrorMessage();
     
   
     
		if($this->store->_checkMysqlTable($err)){
			if($word){
			     $p->num_rows($p->setLimitQuery($this->store->_findSql($word,$p->limit()),$db));
			     $data = $db->text->query($this->store->_findSql($word,' ORDER BY  `'.$this->store->optionsform['order by']['name'].'` '.$this->store->optionsform['order by']['method'].' '.$p->limit()));
			}else{
			     $p->num_rows($p->setLimitQuery($this->store->_selectSql($p->limit()),$db));
			     
			     $data = $db->text->query($this->store->_selectSql(' ORDER BY  `'.$this->store->optionsform['order by']['name'].'` '.$this->store->optionsform['order by']['method'].' '.$p->limit()));
			}
		}
		

		$db->getError = false;
		
		$form.='<form action="" method="post" autocomplete="on" id="getformdata">';
		$form.='<div class="gbar"><input type="text" name="'.$this->store->optionsform['searchstring'].'" id="'.$this->store->optionsform['searchstring'].'" placeholder="'.lang::str('Sök').'" ><input type="submit" value="'.lang::str('Sök').'"></div>';
		
		if($this->store->optionsform['title']==null){
			$form.='<h1>';
			foreach($this->store->tabletree as $k => $v){
			     $form.=' '.$k.',';
			}
			if(substr($form, -1) ==","){
			   $form = substr_replace($form ,"",-1);	
			}
			$form.='</h1>';
		}else{
		$form.=$this->store->optionsform['title'];
		}
		
		$count = $p->num_rows;
		
		if($word){
		$form.=lang::str('Sökordet är "%s" som gav %d',$word,$count);
		}
		
		if(empty($err) && !empty($data)){
			
		
		$data = $this->store->fixData($data);
			
		$form.=$this->store->htmlTable($data);
		}
		
		$form.="</form>";
		
		$form.='<li class="prev">';
		
		if($p->IsPrev()){
			$form.='<a href="?'.$this->store->optionsform['searchstring'].'='.$word.'&'.$this->store->optionsform['stringnavigation'].'='.$p->prev_page.'">'.lang::str('Föregående').'</a>';
		}else{
			$form.='<span>'.lang::str('Föregående').'</span>';
		}
	
		$form.='</li>';
		$number = 1;
		
		for($number; $number <= $p->num_pages; $number +=1 ){
		    
		    $form.='<li>';
			    $form.='<a href="?'.$this->store->optionsform['searchstring'].'='.$word.'&'.$this->store->optionsform['stringnavigation'].'='.$number.'">'.$number.'</a>';
		    $form.='</li>';
		}

	
		$form.='<li class="next">';
		if($p->IsNext()){ 
		$form.='<a href="?'.$this->store->optionsform['stringnavigation'].'='.$p->next_page.'">'.lang::str('Nästa').'</a>';
		}else{
		    $form.='<span>'.lang::str('Nästa').'</span>';
		}
		$form.='</li>';
      
		return $form;
     
	}
	
	function updateFormData(){
		
		$this->store->makeTableTree();
		
		$db = new DatabaseConnection();
		$db->getError = true;
    
		if(request::req('submit')==lang::str('Ta bort')){
		   $db->delete($this->store->_removeRows(request::req('del')));
		}
     
		if(request::req('submit')==lang::str('Spara')){
			foreach($this->store->_updateRows() as $sql){
				$db->updateRow($sql);
			}
		}
		
		$word = trim(request::req($this->store->optionsform['searchstring']));
		
		$form = "";
		$p = new Pagination($this->store->optionsform['pagination']);
		$p->currentPage(request::req($this->store->optionsform['stringnavigation']));
		
		  if($word){

			$p->num_rows($p->setLimitQuery($this->store->_findSql($word,$p->limit())));
			$data = $db->text->query($this->store->_findSql($word,$p->limit()));
			}else{
			$p->num_rows($p->setLimitQuery($this->store->_selectSql($p->limit()),$db));

			$data = $db->text->query($this->store->_selectSql($p->limit()));
		}
	
		$err = $db->getErrorMessage();
		$db->getError = false;
		
		$form.='<form  action="" method="post" autocomplete="on" id="getformdata">';
		$form.='<div class="gbar"><input type="text" name="'.$this->store->optionsform['searchstring'].'" id="'.$this->store->optionsform['searchstring'].'" placeholder="'.lang::str('Sök').'" ><input type="submit" value="'.lang::str('Sök').'"></div>';
		
		if($this->store->optionsform['title']==null){
			$form.='<h1>';
			foreach($this->store->tabletree as $k => $v){
			     $form.=' '.$k.',';
			}
			if(substr($form, -1) ==","){
			$form = substr_replace($form ,"",-1);	
			}
		$form.='</h1>';
		}else{
			$form.=$this->store->optionsform['title'];
			
		}
		
		$count = $p->num_rows;
		  
		if($word){
		   $form.=lang::str('Sökordet är "%s" som gav %d',$word,$count);
		}

		if(empty($err) && !empty($data)){
		
		      $form.=$this->store->htmlTableEdit($data);
		}
		
	
		
		$form.='<li class="prev">';
		
		if($p->IsPrev()){
			$form.='<a href="?'.$this->store->optionsform['searchstring'].'='.$word.'&'.$this->store->optionsform['stringnavigation'].'='.$p->prev_page.'">'.lang::str('Föregående').'</a>';
		}else{
			$form.='<span>'.lang::str('Föregående').'</span>';
		}
	
		$form.='</li>';
		$number = 1;
		
		for($number; $number <= $p->num_pages; $number +=1 ){
		    
		    $form.='<li>';
			    $form.='<a href="?'.$this->store->optionsform['searchstring'].'='.$word.'&'.$this->store->optionsform['stringnavigation'].'='.$number.'">'.$number.'</a>';
		    $form.='</li>';
		}

	
		$form.='<li class="next">';
		if($p->IsNext()){ 
		$form.='<a href="?'.$this->store->optionsform['stringnavigation'].'='.$p->next_page.'">'.lang::str('Nästa').'</a>';
		}else{
		    $form.='<span>'.lang::str('Nästa').'</span>';
		}
		$form.='</li>';
		  $form.='<input type="submit" name="submit" value="'.lang::str('Ta bort').'"><input type="submit" name="submit" value="'.lang::str('Spara').'">';
		    $form.="</form>";
		return $form;
	}
}


?>