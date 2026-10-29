<?php 
class Rtable{
	private $data = array();
	public $sort = array();
	public $header = array();
	public $header_attr = array();
	private $lab = array();
	private $url = array();
	public $words = array();
	public $p =false;
	function __construct($lab,$datas){
		
		foreach($lab as $title => $data){
				
			if(array_key_exists('sort', $data) && $data['sort'] ==true){
				$this->sort = $title;
			}
					
			if(array_key_exists('title', $data) && $data['title'] ==true){
				$this->newtitle[$title] = $data['title'];
			}
					
			if(array_key_exists('cell', $data) && $data['cell'] !=""){
				$this->header[$title] = $data['cell'];
			}
					
			if(array_key_exists('attr', $data) && $data['attr'] !=""){
				$this->header[$title] = $data['attr'];
			}
				
		}
		
		$this->lab = $lab;
		$this->data = $datas;
	}
	
	function getData(){
		return $this->data;
	}
	
	function getHTML(){
		if($this->data!=""){
			
			$t = '<table><tr>';
			foreach($this->data as $k){
				foreach($k as $e => $v){
					$r ="";
					$s ="";
					$this->url[$e] = array();
					if(array_key_exists($e,$this->lab)){
						if(array_key_exists('title',$this->lab[$e])){
							$r = $this->lab[$e]['title'];
						}else{
							$r = $e;
						}
				
						if($this->words){
							$this->url[$e] = array_merge($this->url[$e], $this->words);
						}
				
						if($this->p){
							$page = $this->p->page;

							$this->url[$e] = array_merge($this->url[$e], array('page'=>$page));
						}
				
				
						if(array_key_exists('sort',$this->lab[$e])){
					
							$this->url[$e] = array_merge($this->url[$e], $this->sort[$this->sort[$e]]['_sort']);
				
					
						}
						if($this->url && array_key_exists($e,$this->url)){
							$e = '<a href="?'.http_build_query($this->url[$e]).'">'.$r.'</a>';	
						}
						
					/*	if(array_key_exists('attr',$this->lab[$e])){
							$t .="<td '.$this->lab[$e]['attr'].' >".$e.'</td">';	
						}*/
							$t .="<td>".$e.'</td">';	
						
						
					}else{
						$t .="<td>".$e.'</td">';	
					}
				
			
			
				}
				break;
			}
			$t .= '</tr>';
			foreach($this->data as $k){
				$t .= '<tr>';
				foreach($k as $e => $v){
					$t .="<td>".$v.'</td">';
				}
				$t .= '</tr>';
			}
  	
			$t .= '</table>';
			return $t;
		}else{
			return "";
		}
	}

	function render(){
		if($this->data!=""){
			
			$t = "";
			$d = array();
			foreach($this->data as $s => $k){
				foreach($k as $e => $v){
					$r ="";
					$s =0;
					$this->url[$e] = array();
					if(array_key_exists($e,$this->lab)){
						if(array_key_exists('title',$this->lab[$e])){
							$r = $this->lab[$e]['title'];
						}else{
							$r = $e;
						}
				
						if($this->words){
							$this->url[$e] = array_merge($this->url[$e], $this->words);
						}
				
						if($this->p){
							$page = $this->p->page;

							$this->url[$e] = array_merge($this->url[$e], array('page'=>$page));
						}
				
				
						if(array_key_exists('sort',$this->lab[$e])){
					
							$this->url[$e] = array_merge($this->url[$e], $this->sort[$this->sort[$e]]['_sort']);
				
					
						}
						if($this->url && array_key_exists($e,$this->url)){
							$r = '<a href="?'.http_build_query($this->url[$e]).'">'.$r.'</a>';	
						}
						$d[$s][$e] = $r;	
					}else{
						$d[$s][$e] =$e;	
					}
				
			
			
				}
				break;
			}

			foreach($this->data as $s => $k){
		
				foreach($k as $e => $v){
					$d[$s][$e] =$v;
				}
		
			}
  	
			
			return $d;
		}else{
			return "";
		}
	}

	function 	prevLink(){
		$html ="";

		if($this->p->IsPrev()){ 
			if(strchr($_SERVER['REQUEST_URI'],"&")){
				$html .='<a href="'.rtrim(preg_replace('/page=[\d]{0,}(?:&|)/', '', $_SERVER['REQUEST_URI']),'&').'&page='.$this->p->prev_page.'">'._('Föregående').'</a>';
			}else{
				$html .='<a href="?page='.$this->p->prev_page.'">'._('Föregående').'</a>';
			}
		}else{
			
			$html .='<span>'._('Föregående').'</span>';
	
		}
	 
		return $html;
	}
	
	function nextLink(){
		$html ="";
  	  	
		if($this->p->IsNext()){ 
			if(strchr($_SERVER['REQUEST_URI'],"&")){
				$html .='<a href="'.rtrim(preg_replace('/page=[\d]{0,}(?:&|)/', '', $_SERVER['REQUEST_URI']),'&').'&page='.$this->p->next_page.'">'._('Nästa').'</a>';
			}else{		
				$html .='<a href="?page='.$this->p->next_page.'">'._('Nästa').'</a>';
			}

		}else{
			$html .='<span>'._('Nästa').'</span>';
		}
	 
		return $html;
	}
  
	function centerLinks(){
		$html ="";
  	  	
		$number = 1;
		$html .='<ul>';
		for($number; $number <= $this->p->num_pages; $number +=1 ){
		
			$html .='<li>';
			if($_SERVER['REQUEST_URI']){
				$html .='<a href="'.rtrim(preg_replace('/page=[\d]{0,}(?:&|)/', '', $_SERVER['REQUEST_URI']),'&').'&page='.$number.'">'.$number.'</a>';
			}else{
				$html .='<a href="?page='.$number.'">'.$number.'</a>';
			}
			$html .='</li>';
		}
	 
		return $html;
	}	   
  
	function renderLinks(){
		$html ="";

		if($this->p->IsPrev()){ 
			if($_SERVER['REQUEST_URI']){
				$html .='<a href="'.rtrim(preg_replace('/page=[\d]{0,}(?:&|)/', '', $_SERVER['REQUEST_URI']),'&').'&page='.$this->p->prev_page.'">'._('Föregående').'</a>';
			}else{
				$html .='<a href="?page='.$this->p->prev_page.'">'._('Föregående').'</a>';
			}
		}else{
			
			$html .='<span>'._('Föregående').'</span>';
	
		}
		
		$number = 1;
		$html .='<ul>';
		for($number; $number <= $this->p->num_pages; $number +=1 ){
		
			$html .='<li>';
			if($_SERVER['REQUEST_URI']){
				$html .='<a href="'.rtrim(preg_replace('/page=[\d]{0,}(?:&|)/', '', $_SERVER['REQUEST_URI']),'&').'&page='.$number.'">'.$number.'</a>';
			}else{
				$html .='<a href="?page='.$number.'">'.$number.'</a>';
			}
			$html .='</li>';
	
		}

		if($this->p->IsNext()){ 
			if($_SERVER['REQUEST_URI']){
				$html .='<a href="'.rtrim(preg_replace('/page=[\d]{0,}(?:&|)/', '', $_SERVER['REQUEST_URI']),'&').'&page='.$this->p->next_page.'">'._('Nästa').'</a>';
			}else{		
				$html .='<a href="?page='.$this->p->next_page.'">'._('Nästa').'</a>';
			}

		}else{
			$html .='<span>'._('Nästa').'</span>';
		}
	
		return $html;
  
  
	}	

}