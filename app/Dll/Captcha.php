<?php

class Captcha{
    
    public $length = "";
    public $image_type = "";
    private $str = "";
    public $font = "";
    public $char_width = "";
    public $text_color = "";
    public $background_color = "";
    public $cases = "";
    public $image_height = "";
    public $image_padding = "";
    

    function __construct($length=null,$image_type=null,$font=null,$char_width=null,$text_color=null,$background_color = null, $cases = null,$image_height=null, $image_padding=null){
      
      $args = array(
            'length' => $length,
            'image_type' => $image_type,
            'font' => $font,
            'char_width' => $char_width,
            'text_color' => $text_color,
            'background_color' => $background_color,
            'cases' => $cases,
            'image_height' => $image_height,
            'image_padding' => $image_padding
        );
       
       
       
        foreach($args as $key => $value){
            if(empty($value)){
                $this->$key = config::get('captcha.'.$key);
            }else{
                $this->$key = $value;
            }
            
        }
 
	$this->StringGen();
          
        session::set('captcha_hash',sha1($this->str));
             
	$this->SendHeader();
	$this->MakeCaptcha();
     
    }
    

    // Generate CAPTCHA string
    // String Type:
    //
    // 0 : Lowercase Letters (a-z).
    // 1 : Uppercase Letters (A-Z).
    // 2 : Numbers Only (0-9).
    // 3 : Letters Only (upper and lower case).
    // 4 : Lowercase Letters and Numbers.
    // 5 : Uppercase Letters and Numbers.
    // 6 : All together


    private function StringGen() {
    
            $uppercase  = range('A', 'Z');
            $lowercase  = range('a', 'z');
            $numeric    = range(0, 9);
            
            $char_pool  = array();
            
            switch($this->cases) {
                    case 0: $char_pool = $lowercase; break;
                    case 1: $char_pool = $uppercase; break;
                    case 2: $char_pool = $numeric; break;
                    case 3: $char_pool = array_merge($uppercase, $lowercase); break;
                    case 4: $char_pool = array_merge($lowercase, $numeric); break;
                    case 5: $char_pool = array_merge($uppercase, $numeric); break;
                    case 6: $char_pool = array_merge($uppercase, $lowercase, $numeric); break;
                    default:$char_pool = array_merge($uppercase, $numeric);
            }
    
            $pool_length = count($char_pool) - 1;
    
            for($i = 0; $i < $this->length; $i++) {
                    $this->str .= $char_pool[mt_rand(0, $pool_length)];
            }
    }
    
    
    private function SendHeader(){

	switch($this->image_type) {
		case 'jpeg': header('Content-type: image/jpeg'); break;
		case 'png': header('Content-type: image/png'); break;
		case 'gif': header('Content-type: image/gif'); break;
		default: header('Content-type: image/png'); break;
	}
    }

   private function makecaptcha(){
    
           $imagelength = $this->length * $this->char_width + $this->image_padding;
            $image  = imagecreate($imagelength, $this->image_height) or die("Cannot Initialize new GD image stream, this will NOT work!");;
            $bgcolor = imagecolorallocate(
                    $image,
                    hexdec(substr($this->background_color,0,2)),
                    hexdec(substr($this->background_color,2,2)),
                    hexdec(substr($this->background_color,4,2))
            );
    
            $stringcolor = imagecolorallocate(
                    $image,
                    hexdec(substr($this->text_color,0,2)),
                    hexdec(substr($this->text_color,2,2)),
                    hexdec(substr($this->text_color,4,2))
            );
            $linecolor   = imagecolorallocate($image, 0, 0, 0);
            imagettftext($image, $this->char_width, 0, $this->image_padding, 21, $stringcolor, $this->font, $this->str);
            
            for ($i=1;$i<500;$i++)
                {
                    $cor_x = rand(1,$imagelength);
                    $cor_y = rand(1,$this->image_height);
                    imagesetpixel($image,$cor_x,$cor_y,$stringcolor);
                }
    
            switch($this->image_type) {
                    case 'jpeg': imagejpeg($image); break;
                    case 'png': imagepng($image); break;
                    case 'gif': imagegif($image); break;
                    default: imagepng($image); break;
            }
    
            imagedestroy($image);
  
    }

    
   public static function vaild($str){
        if(sha1($str)==session::get('captcha_hash')){
            return true;
        }else{
            return false;
        }
    }
    
    
}
