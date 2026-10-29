<?
class Slug implements ArrayAccess{

      protected $original = null;
      protected $slug = null;
      protected $options = array(
            'to_lower'        => true,
            'max_length'      => null,
            'prefix'          => null,
            'postfix'         => null,
            'char_map'        => null,
            'replacements'    => array(),
            'seperator_char'  => '-'
      );
      
      protected $char_map = array(
		// Latin
		'À' => 'A', 'Á' => 'A', 'Â' => 'A', 'Ã' => 'A', 'Æ' => 'AE', 'Ç' => 'C', 
		'È' => 'E', 'É' => 'E', 'Ê' => 'E', 'Ë' => 'E', 'Ì' => 'I', 'Í' => 'I', 'Î' => 'I', 'Ï' => 'I', 
		'Ð' => 'D', 'Ñ' => 'N', 'Ò' => 'O', 'Ó' => 'O', 'Ô' => 'O', 'Õ' => 'O', 'Ő' => 'O', 
		'Ø' => 'O', 'Ù' => 'U', 'Ú' => 'U', 'Û' => 'U', 'Ü' => 'U', 'Ű' => 'U', 'Ý' => 'Y', 'Þ' => 'TH', 
		'ß' => 'ss', 
		'à' => 'a', 'á' => 'a', 'â' => 'a', 'ã' => 'a', 'æ' => 'ae', 'ç' => 'c', 
		'è' => 'e', 'é' => 'e', 'ê' => 'e', 'ë' => 'e', 'ì' => 'i', 'í' => 'i', 'î' => 'i', 'ï' => 'i', 
		'ð' => 'd', 'ñ' => 'n', 'ò' => 'o', 'ó' => 'o', 'ô' => 'o', 'õ' => 'o', 'ő' => 'o', 
		'ø' => 'o', 'ù' => 'u', 'ú' => 'u', 'û' => 'u', 'ü' => 'u', 'ű' => 'u', 'ý' => 'y', 'þ' => 'th', 
		'ÿ' => 'y','ä' => 'ae','Ä' => 'Ae','Ö' => 'Oe','ö' => 'oe','å' => 'ao','Å' => 'Ao',
 
		// Latin symbols
		'©' => '(c)',
 
		// Greek
		'Α' => 'A', 'Β' => 'B', 'Γ' => 'G', 'Δ' => 'D', 'Ε' => 'E', 'Ζ' => 'Z', 'Η' => 'H', 'Θ' => '8',
		'Ι' => 'I', 'Κ' => 'K', 'Λ' => 'L', 'Μ' => 'M', 'Ν' => 'N', 'Ξ' => '3', 'Ο' => 'O', 'Π' => 'P',
		'Ρ' => 'R', 'Σ' => 'S', 'Τ' => 'T', 'Υ' => 'Y', 'Φ' => 'F', 'Χ' => 'X', 'Ψ' => 'PS', 'Ω' => 'W',
		'Ά' => 'A', 'Έ' => 'E', 'Ί' => 'I', 'Ό' => 'O', 'Ύ' => 'Y', 'Ή' => 'H', 'Ώ' => 'W', 'Ϊ' => 'I',
		'Ϋ' => 'Y',
		'α' => 'a', 'β' => 'b', 'γ' => 'g', 'δ' => 'd', 'ε' => 'e', 'ζ' => 'z', 'η' => 'h', 'θ' => '8',
		'ι' => 'i', 'κ' => 'k', 'λ' => 'l', 'μ' => 'm', 'ν' => 'n', 'ξ' => '3', 'ο' => 'o', 'π' => 'p',
		'ρ' => 'r', 'σ' => 's', 'τ' => 't', 'υ' => 'y', 'φ' => 'f', 'χ' => 'x', 'ψ' => 'ps', 'ω' => 'w',
		'ά' => 'a', 'έ' => 'e', 'ί' => 'i', 'ό' => 'o', 'ύ' => 'y', 'ή' => 'h', 'ώ' => 'w', 'ς' => 's',
		'ϊ' => 'i', 'ΰ' => 'y', 'ϋ' => 'y', 'ΐ' => 'i',
 
		// Turkish
		'Ş' => 'S', 'İ' => 'I', 'Ç' => 'C', 'Ü' => 'U', 'Ğ' => 'G',
		'ş' => 's', 'ı' => 'i', 'ç' => 'c', 'ü' => 'u', 'ğ' => 'g', 
 
		// Russian
		'А' => 'A', 'Б' => 'B', 'В' => 'V', 'Г' => 'G', 'Д' => 'D', 'Е' => 'E', 'Ё' => 'Yo', 'Ж' => 'Zh',
		'З' => 'Z', 'И' => 'I', 'Й' => 'J', 'К' => 'K', 'Л' => 'L', 'М' => 'M', 'Н' => 'N', 'О' => 'O',
		'П' => 'P', 'Р' => 'R', 'С' => 'S', 'Т' => 'T', 'У' => 'U', 'Ф' => 'F', 'Х' => 'H', 'Ц' => 'C',
		'Ч' => 'Ch', 'Ш' => 'Sh', 'Щ' => 'Sh', 'Ъ' => '', 'Ы' => 'Y', 'Ь' => '', 'Э' => 'E', 'Ю' => 'Yu',
		'Я' => 'Ya',
		'а' => 'a', 'б' => 'b', 'в' => 'v', 'г' => 'g', 'д' => 'd', 'е' => 'e', 'ё' => 'yo', 'ж' => 'zh',
		'з' => 'z', 'и' => 'i', 'й' => 'j', 'к' => 'k', 'л' => 'l', 'м' => 'm', 'н' => 'n', 'о' => 'o',
		'п' => 'p', 'р' => 'r', 'с' => 's', 'т' => 't', 'у' => 'u', 'ф' => 'f', 'х' => 'h', 'ц' => 'c',
		'ч' => 'ch', 'ш' => 'sh', 'щ' => 'sh', 'ъ' => '', 'ы' => 'y', 'ь' => '', 'э' => 'e', 'ю' => 'yu',
		'я' => 'ya',
 
		// Ukrainian
		'Є' => 'Ye', 'І' => 'I', 'Ї' => 'Yi', 'Ґ' => 'G',
		'є' => 'ye', 'і' => 'i', 'ї' => 'yi', 'ґ' => 'g',
 
		// Czech
		'Č' => 'C', 'Ď' => 'D', 'Ě' => 'E', 'Ň' => 'N', 'Ř' => 'R', 'Š' => 'S', 'Ť' => 'T', 'Ů' => 'U', 
		'Ž' => 'Z', 
		'č' => 'c', 'ď' => 'd', 'ě' => 'e', 'ň' => 'n', 'ř' => 'r', 'š' => 's', 'ť' => 't', 'ů' => 'u',
		'ž' => 'z', 
 
		// Polish
		'Ą' => 'A', 'Ć' => 'C', 'Ę' => 'e', 'Ł' => 'L', 'Ń' => 'N', 'Ó' => 'o', 'Ś' => 'S', 'Ź' => 'Z', 
		'Ż' => 'Z', 
		'ą' => 'a', 'ć' => 'c', 'ę' => 'e', 'ł' => 'l', 'ń' => 'n', 'ó' => 'o', 'ś' => 's', 'ź' => 'z',
		'ż' => 'z',
 
		// Latvian
		'Ā' => 'A', 'Č' => 'C', 'Ē' => 'E', 'Ģ' => 'G', 'Ī' => 'i', 'Ķ' => 'k', 'Ļ' => 'L', 'Ņ' => 'N', 
		'Š' => 'S', 'Ū' => 'u', 'Ž' => 'Z',
		'ā' => 'a', 'č' => 'c', 'ē' => 'e', 'ģ' => 'g', 'ī' => 'i', 'ķ' => 'k', 'ļ' => 'l', 'ņ' => 'n',
		'š' => 's', 'ū' => 'u', 'ž' => 'z'
	);
        
  public function __construct($text, $options = array()){

      $this->original = $text;

      $this->options = array_merge($this->options, $options);
      
      if (null !== $this->options['char_map']){
        $this->char_map = $this->options['char_map'];
      }
        
  }
  
  public function  generateSlug(){
      // Make sure string is in UTF-8 and strip invalid UTF-8 characters
      $str = mb_convert_encoding((string) $this->original, 'UTF-8', mb_list_encodings());;
   
      if ($this['to_lower']){
      $str = mb_strtolower($str, 'UTF-8');
      }
      
      // Make custom replacements
	$str = preg_replace(array_keys($this->options['replacements']), $this->options['replacements'], $str);
	

      // replace the chars in $this->char_map
      $str = strtr($str , $this->char_map);

      // ensure that there are only valid characters in the url (A-Z a-z 0-9, seperator_char)
      $str = preg_replace('/[^A-Za-z0-9_]/', $this['seperator_char'], $str);
  
      // trim the seperator char at the end and teh beginning
      $str = trim($str, $this['seperator_char']);
  
      // remove duplicate seperator chars
      $str = preg_replace('/['.preg_quote($this['seperator_char']).']+/', $this['seperator_char'], $str);
  
      if ($this['max_length']){
        $str = $this->shortenSlug($str, $this['max_length']-mb_strlen($this['prefix'], 'UTF-8')-mb_strlen($this['postfix'], 'UTF-8'));
      }
  
      // Add prefix & postfix
      $this->slug = $this['prefix'].$str.$this['postfix'];
   
  }
  
    /**
   * Shorten the slug.
   */
  protected function shortenSlug($slug, $maxLen){
    // $maxLen must be greater than 1
    if ($maxLen < 1){
      return $slug;
    }

    // check whether there is work to do
    if (strlen($slug) < $maxLen){
      return $slug;
    }

    // cut to $maxLen
    $cutted_slug = trim(substr($slug, 0, $maxLen), $this['seperator_char']);

    // cut to the last position of '-' in cutted string
    $beautified_slug = trim(preg_replace('/[^'.preg_quote($this['seperator_char']).']*$/', '', $cutted_slug), $this['seperator_char']);

    // only return the beautified string when it is long enough
    if (strlen($beautified_slug) < ($maxLen/2)){
      return $cutted_slug;
    }else{
      return $beautified_slug;
    }
  }
  
    /**
   * Returns the slug-string
   *
   * @return string the slug
   * @see generateSlug()
   */
  public function getSlug(){
    if (null === $this->slug){
      $this->generateSlug();
    }
    return $this->slug;
  }
  
   public function __toString(){
    return $this->getSlug();
  }
  
    /**
   * Sets the option associated with the offset (implements the ArrayAccess interface).
   *
   * @param string $offset The option name
   * @param string $value The option value
   */
  public function offsetSet($offset, $value){
    if (is_null($offset)){
      $this->options[] = $value;
    }else{
      $this->options[$offset] = $value;
    }
  }

  /**
   * Returns true if the option exists (implements the ArrayAccess interface).
   *
   * @param  string $name The name of option
   * @return Boolean true if the option exists, false otherwise
   */
  public function offsetExists($offset){
    return isset($this->options[$offset]);
  }

  /**
   * Unsets the option associated with the offset (implements the ArrayAccess interface).
   *
   * @param string $offset The option name
   */
  public function offsetUnset($offset){
    $this->options[$offset] = null;
  }

  /**
   * Returns an option (implements the ArrayAccess interface).
   *
   * @param  string $name The offset of the option to get
   * @return mixed The option if exists, null otherwise
   */
  public function offsetGet($offset){
    return isset($this->options[$offset]) ? $this->options[$offset] : null;
  }
  
      /**
     * Add's _1 to a string or increment the ending number to allow _2, _3, etc
     *
     * @param   string  $str  required
     * @param   string  $separator  What should the duplicate number be appended with
     * @param   string  $first  Which number should be used for the first dupe increment
     * @return  string
     */
  public static  function increment($str, $separator = '-', $first = 1){
    preg_match('/(.+)'.$separator.'([0-9]+)$/', $str, $match);
    return isset($match[2]) ? $match[1].$separator.($match[2] + 1) : $str.$separator.$first;
  }
}