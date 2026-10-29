<?php  class thumbnails{

     function __construct($user=false){
	 	
	 }
	 
	private function addLayoutImage($File){
	 $info = getimagesize($File);
	 list($width, $height) = $info;
     $mime = $info['mime'];
     

    		switch($mime){
            case 'image/jpeg':
                    $image_create_func = 'imagecreatefromjpeg';
                    $image_save_func = 'imagejpeg';
                    $ext = 'jpg';
                    break;

            case 'image/png':
                    $image_create_func = 'imagecreatefrompng';
                    $image_save_func = 'imagepng';
                    $ext = 'png';
                    break;

            case 'image/gif':
                    $image_create_func = 'imagecreatefromgif';
                    $image_save_func = 'imagegif';
                    $ext = 'gif';
                    break;

            default: 
                    throw new Exception('Unknown image type.');
    		}
    		
    		return array(
    		'image_create_func' => $image_create_func, 
    		'image_save_func' => $image_save_func,
    		'ext' => $ext,
    		'width' =>$width, 
    		'height' =>$height,
    		'path' =>$File,
    		);

	 }
	 
	 function addImage($file){
	 	$this->cur = $file;
	 	$this->layout[$this->cur] = $this->addLayoutImage($file);
	 	return $this;
	 }
	 
	 function setResize($width=0,$height=0){
	 	$this->layout[$this->cur]['resize'] = array('width' => $width,'height' => $height);
	 }
	


	 
	 function save(){
	 	
	 
	
	 $images = array($_SERVER['DOCUMENT_ROOT'].$_SERVER['HTTP_path_site'].'1.jpg',$_SERVER['DOCUMENT_ROOT'].$_SERVER['HTTP_path_site'].'2.jpg',$_SERVER['DOCUMENT_ROOT'].$_SERVER['HTTP_path_site'].'3.jpg');
$number_of_images = count($images);

$priority = "columns"; // also "rows"

if($priority == "rows"){
  $rows = 3;
  $columns = $number_of_images/$rows;
  $columns = (int) $columns; // typecast to int. and makes sure grid is even
}else if($priority == "columns"){
  $columns = 3;
  $rows = $number_of_images/$columns;
  $rows = (int) $rows; // typecast to int. and makes sure grid is even
}
$width = 150; // image width
$height = 150; // image height

$background = imagecreatetruecolor(($width*$columns), ($height*$rows)); // setting canvas size
$output_image = $background;

// Creating image objects
$image_objects = array();
for($i = 0; $i < ($rows * $columns); $i++){
  $image_objects[$i] = imagecreatefromjpeg($images[$i]);
}

// Merge Images
$step = 0;
for($x = 0; $x < $columns; $x++){
  for($y = 0; $y < $rows; $y++){
    imagecopymerge($output_image, $image_objects[$step], ($width * $x), ($height * $y), 0, 0, $width, $height, 100);
    $step++; // steps through the $image_objects array
  }
}

imagejpeg($output_image, $_SERVER['DOCUMENT_ROOT'].$_SERVER['HTTP_path_site'].'test.jpg');
imagedestroy($output_image);

	 }
}