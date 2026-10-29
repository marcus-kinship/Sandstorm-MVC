<?

/**

 * Run a given route
 * 
 *
 * @category _("Route class")
 */

class SiteRouter
{


   public $sitemap;
   public $routname;
   public $extend;
   public static $address;
   public static $scheme;
   public static $url;
   public static $REQUEST_URI;
   public $time;
   public static $addclass = array();


   /**
    * This function
    * sends the visitor to the desired address.
    * You can also use a bool value you need check before
    * sends the visitor to the desired address.
    *
    * The first parameter is url
    * The second parameter is bool and it is optional
    * @param string $url, bool $bool
    */
   public static function wayto($url, $bool = true)
   {

      if (!headers_sent()) {
         if ($bool) {
            header('Location: ' . $url, true, 301);
            exit;
         }
      }
   }

   /**
    * This function
    * verifying if the visitor comes from a mobile device.
    * @return bool;
    */
   public function nav()
   {

      if (preg_match('/(' . implode("|", config::get('mobilebrowsers.list')) . ')/', $_SERVER['HTTP_USER_AGENT']) == true) {
         return true;
      } else {
         // return true;
         return false;
      }
   }

   public static  function matching()
   {

      $REQUEST_URI = explode("?", $_SERVER["REQUEST_URI"]);
      $REQUEST_URI = substr($REQUEST_URI[0], 1);

      if ($REQUEST_URI == "") {
         return array();
      }

      $url = parse_url($REQUEST_URI, PHP_URL_PATH);
      $REQUEST_URI = explode("/", trim($url, "/"));


      return $REQUEST_URI;
   }

   /**
    * Funktionen back() validerar och sanerar HTTP_REFERER, och returnerar 
    * en giltig referer-URL eller en fördefinierad fallback-URL.
    *
    * @param string $default - En fallback-URL som returneras om HTTP_REFERER inte är giltig
    *
    * @return string - En giltig referer-URL eller fallback-URL
    */
   public static function back($default = "")
   {
      // Hämta referer-URL från $_SERVER-variabeln

      if (!key_exists("HTTP_REFERER", $_SERVER)) {
         // Om referer-URL inte är satt, returnera fallback-URL
         return $default;
      }
      $input = $_SERVER["HTTP_REFERER"];
      // Ta bort whitespace från början och slutet av strängen
      $input = trim($input);

      // Ta bort backslashes
      $input = stripslashes($input);

      // Hantera HTML-tecken för att undvika XSS-attacker
      $input = htmlspecialchars($input, ENT_QUOTES, 'UTF-8');

      // Kontrollera om referer-URL är giltig
      if ($input === $_SERVER["HTTP_REFERER"]) {
         // Om referer-URL är giltig, returnera den
         return $input;
      }

      // Om referer-URL inte är giltig, returnera fallback-URL
      return $default;
   }


   /** 
    * Hämtar en del av urlen adressen hjälp av ett nummer
    * @param int $slug
    * @return string 
    */
   public static function getsPartUrl($slug)
   {
      self::$address = self::matching();
      if (array_key_exists(($slug - 1), self::$address)) {
         return self::$address[$slug - 1];
      } else {
         return false;
      }
   }
}