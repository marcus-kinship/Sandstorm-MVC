<?

/**
 * Klassen Resizing för bildmanipulering.
 *
 * @category _("Utility class")
 */
class Resizing
{
   /**
    * @var resource Identifierare för bildresurs.
    */
   private $image;

   /**
    * @var int Konstant för bildtyp (IMAGETYPE_JPEG, IMAGETYPE_GIF, IMAGETYPE_PNG, etc.).
    */
   private $image_type;

   /**
    * Laddar en bild från en fil och bestämmer bildtypen.
    *
    * @param string $filename Filvägen till bilden.
    *
    * @throws SystemException Kastar ett undantag om bildtypen inte stöds.
    */
   function load($filename)
   {
      // Hämta information om bilden, inklusive bildtypen
      $image_info = getimagesize($filename);
      $this->image_type = $image_info[2];

      // Skapa en bildresurs baserat på bildtypen
      switch ($this->image_type) {
         case IMAGETYPE_JPEG:
            $this->image = imagecreatefromjpeg($filename);
            break;
         case IMAGETYPE_GIF:
            $this->image = imagecreatefromgif($filename);
            break;
         case IMAGETYPE_PNG:
            $this->image = imagecreatefrompng($filename);
            break;
         case IMAGETYPE_BMP:
            $this->image = imagecreatefrombmp($filename);
            break;
         case IMAGETYPE_WEBP:
            $this->image = imagecreatefromwebp($filename);
            break;
         default:
            // Kasta undantag om bildtypen inte stöds
            throw new SystemException(sprintf(_("Bildtypen stöds inte: %s"), $this->image_type));
      }
   }

   /**
    * Smart ändring av storlek för att anpassa till det nya måttet och fylla överblivet område med angiven bakgrundsfärg.
    *
    * @param int $width Den nya bredden på bilden.
    * @param int $height Den nya höjden på bilden.
    * @param int $R Röd komponent för bakgrundsfärgen.
    * @param int $G Grön komponent för bakgrundsfärgen.
    * @param int $B Blå komponent för bakgrundsfärgen.
    */
   public function smartResize($width, $height, $R, $G, $B)
   {
      // Skapa en ny bild med angiven bredd och höjd
      $w = $width;
      $h = $height;
      $redimg = imagecreatetruecolor($w, $h);

      // Sätt bakgrundsfärgen till den angivna färgen
      $color = imagecolorallocate($redimg, $R, $G, $B);
      imagefill($redimg, 0, 0, $color);

      // Hämta aktuella bredd och höjd på bilden
      $nheight = $this->getHeight();
      $nwidth = $this->getWidth();

      // Beräkna nya mått för att behålla proportionerna
      if ($nwidth > $nheight) {
         $new_width = $w;
         $new_height = intval($nheight * $w / $nwidth);
      }
      if ($nwidth < $nheight) {
         $new_height = $h;
         $new_width = intval($nwidth * $h / $nheight);
      }
      if ($nwidth == $nheight) {
         $new_width = $w;
         $new_height = $h;
      }

      // Ändra storlek på bilden enligt de nya måtten
      $this->resize($new_width, $new_height);
      $t =  0;

      // Centrera bilden om överblivet utrymme finns
      if ($new_height <= $h) {
         $t++;
         $w_point = ceil((($w - $new_width) / 2));
      } else {
         $t = 0;
         $this->smartCrop($new_width, $h, ceil((($w - $new_width) / 2)), ceil((($h - $new_height) / 2)), ceil((($w - $new_width) / 2)), ceil((($h - $new_height) / 2)));
      }

      if ($new_width <= $w) {
         $t++;
         $h_point = ceil((($h - $new_height) / 2));
      } else {
         $t = 0;
         $w_point = ceil((($w - $new_width) / 2));
         $this->smartCrop($w, $new_height, ceil((($w - $new_width) / 2)), ceil((($h - $new_height) / 2)), ceil((($w - $new_width) / 2)), ceil((($h - $new_height) / 2)));
      }

      // Kopiera den ändrade bilden till den nya bilden med bakgrundsfärgen
      if ($t == 2) {
         imagecopyresampled($redimg, $this->image, $w_point, $h_point, 0, 0, $new_width, $new_height, $new_width, $new_height);
         $this->image = $redimg;
      }
   }

   /**
    * Rotera bilden.
    *
    * @param int $degrees Grader att rotera.
    */
   function rotate($degrees = 0)
   {
      // Rotate
      $this->image = imagerotate($this->image, $degrees, 0);
   }

   /**
    * Spara bilden till en fil.
    *
    * @param string $filename Utdatafilens namn.
    * @param int|null $image_type Utdata bildtyp (IMAGETYPE_JPEG, IMAGETYPE_GIF, IMAGETYPE_PNG).
    * @param int $compression Kompressionsnivå för JPEG-bilder.
    * @param int|null $permissions Filbehörigheter.
    */
   function save($filename, $image_type = null, $compression = 75, $permissions = null)
   {
      // Om bildformat inte anges, använd det befintliga formatet
      if ($image_type == null) {
         $image_type = $this->image_type;
      }

      // Ställer in alfablandning och sparar alfakanalen för bilder med genomskinlighet
      imagealphablending($this->image, false);
      imagesavealpha($this->image, true);

      // Beroende på valt bildformat, spara bilden med angivet filnamn och kompressionsnivå
      if ($image_type == IMAGETYPE_JPEG) {
         imagejpeg($this->image, $filename, $compression);
      } elseif ($image_type == IMAGETYPE_GIF) {
         imagegif($this->image, $filename);
      } elseif ($image_type == IMAGETYPE_PNG) {
         imagepng($this->image, $filename);
      }

      // Om behörigheter anges, tilldela dem till den sparade filen
      if ($permissions != null) {
         chmod($filename, $permissions);
      }
   }

   /**
    * Visar bilden i det valda bildformatet eller som JPEG om inget annat anges.
    *
    * @param int $image_type Bildformatet (IMAGETYPE_JPEG, IMAGETYPE_GIF, IMAGETYPE_PNG).
    */
   function output($image_type = IMAGETYPE_JPEG)
   {
      // Ställer in alfablandning och sparar alfakanalen för bilder med genomskinlighet
      imagealphablending($this->image, false);
      imagesavealpha($this->image, true);

      // Beroende på valt bildformat, visar bilden
      if ($image_type == IMAGETYPE_JPEG) {
         imagejpeg($this->image);
      } elseif ($image_type == IMAGETYPE_GIF) {
         imagegif($this->image);
      } elseif ($image_type == IMAGETYPE_PNG) {
         imagepng($this->image);
      }
   }

   /**
    * Hämtar bredden på bilden.
    *
    * @return int Bredden på bilden.
    */
   function getWidth()
   {
      return imagesx($this->image);
   }

   /**
    * Hämtar höjden på bilden.
    *
    * @return int Höjden på bilden.
    */
   function getHeight()
   {
      return imagesy($this->image);
   }

   /**
    * Sätter en bakgrundsfärg på bilden.
    *
    * @param int $g Grön komponent för bakgrundsfärgen.
    * @param int $r Röd komponent för bakgrundsfärgen.
    * @param int $b Blå komponent för bakgrundsfärgen.
    * @param int $width Bredden på den nya bilden.
    * @param int $height Höjden på den nya bilden.
    */
   function backgroundcolor($g, $r, $b, $width, $height)
   {
      $redimg = imagecreatetruecolor($width, $height);

      // Sätter bakgrundsfärgen
      $red = imagecolorallocate($redimg, $g, $r, $b);
      imagefill($redimg, 0, 0, $red);

      // Byter till den nya bilden med bakgrundsfärgen
      $this->image = $redimg;
   }

   /**
    * Lägger till en vattenstämpel på bilden.
    *
    * @param mixed $y Y-koordinat för vattenstämpeln. Kan vara "top", "bottom", numerisk koordinat.
    * @param mixed $x X-koordinat för vattenstämpeln. Kan vara "left", "right", numerisk koordinat.
    * @throws SystemException Om stämpelkonfigurationen saknas eller om koordinaterna inte är giltiga.
    */
   function watermark($y, $x)
   {
      $stamp = imagecreatefrompng(config::get('stamp'));
      $sx = imagesx($stamp);
      $sy = imagesy($stamp);

      if ($x == "left") {
         $marge_right = $this->getWidth() - 10;
      } elseif (is_numeric($x)) {
         $marge_right = $x;
      } elseif ($x == "right") {
         $marge_right = 10;
      } else {
         throw new SystemException("Ogiltig X-koordinat för vattenstämpeln");
      }

      if ($y == "top") {
         $marge_bottom = $this->getHeight() - 10;
      } elseif (is_numeric($y)) {
         $marge_bottom = $y;
      } elseif ($y == "bottom") {
         $marge_bottom = 10;
      } else {
         throw new SystemException("Ogiltig Y-koordinat för vattenstämpeln");
      }

      // Kopierar stämpelbilden på vår bild med marginaljusteringar och bildbredden
      imagecopy($this->image, $stamp, imagesx($this->image) - $sx - $marge_right, imagesy($this->image) - $sy - $marge_bottom, 0, 0, imagesx($stamp), imagesy($stamp));
   }

   /**
    * Skapar en miniatyrbild av bilden med en given storlek.
    *
    * @param int $size Storleken på miniatyrbilden.
    */
   function thumbnails($size)
   {
      $nheight = $this->getHeight();
      $nwidth = $this->getWidth();

      if ($nwidth > $nheight) {
         $new_width = $size;
         $new_height = intval($nheight * $size / $nwidth);
      } elseif ($nwidth < $nheight) {
         $new_height = $size;
         $new_width = intval($nwidth * $size / $nheight);
      } else {
         $new_width = $size;
         $new_height = $size;
      }

      $this->resize($new_width, $new_height);
   }

   /**
    * Skapar en miniatyrbild av bilden med given bredd och höjd.
    *
    * @param int $width Bredden på miniatyrbilden.
    * @param int $height Höjden på miniatyrbilden.
    */
   function thumbnailsTo($width, $height)
   {
      $this->resize($width, $height);
   }

   /**
    * Ändrar storleken på bilden till given höjd och behåller proportionerna.
    *
    * @param int $height Den önskade höjden.
    */
   function resizeToHeight($height)
   {
      $ratio = $height / $this->getHeight();
      $width = $this->getWidth() * $ratio;
      $this->resize($width, $height);
   }

   /**
    * Ändrar storleken på bilden till given bredd och behåller proportionerna.
    *
    * @param int $width Den önskade bredden.
    */
   function resizeToWidth($width)
   {
      $ratio = $width / $this->getWidth();
      $height = $this->getHeight() * $ratio;
      $this->resize($width, $height);
   }

   /**
    * Skalar bilden med en given faktor.
    *
    * @param int $scale Skalfaktorn (procent).
    */
   function scale($scale)
   {
      $width = $this->getWidth() * $scale / 100;
      $height = $this->getHeight() * $scale / 100;
      $this->resize($width, $height);
   }

   /**
    * Smart beskär bilden till önskad storlek och position.
    *
    * @param int $width Önskad bredd efter beskärning.
    * @param int $height Önskad höjd efter beskärning.
    * @param int $x X-koordinat för startpositionen.
    * @param int $y Y-koordinat för startpositionen.
    * @param int $x1 X-koordinat för slutpositionen.
    * @param int $y1 Y-koordinat för slutpositionen.
    */
   function smartCrop($width, $height, $x, $y, $x1, $y1)
   {
      $new_image = imagecreatetruecolor($width, $height);

      if ($this->image_type == IMAGETYPE_GIF || $this->image_type == IMAGETYPE_PNG) {
         imagealphablending($new_image, false);
         imagesavealpha($new_image, true);
      }

      // Kopierar en del av bilden till den nya bilden med givna koordinater och storlek
      imagecopy($new_image, $this->image, $x1, $y1, $x, $y, $this->getWidth(), $this->getHeight());
      $this->image = $new_image;
   }

   /**
    * Beskär bilden till given bredd, höjd och position.
    *
    * @param int $width Önskad bredd efter beskärning.
    * @param int $height Önskad höjd efter beskärning.
    * @param int $x X-koordinat för startpositionen.
    * @param int $y Y-koordinat för startpositionen.
    * @throws SystemException Om bredd eller höjd inte är större än 0.
    */
   function crop($width, $height, $x, $y)
   {
      if ($width > 0 && $height > 0) {
         $new_image = imagecreatetruecolor($width, $height);
      } else {
         throw new SystemException(_("Bredd och höjd måste vara större än 0"));
      }

      if ($this->image_type == IMAGETYPE_GIF || $this->image_type == IMAGETYPE_PNG) {
         imagealphablending($new_image, false);
         imagesavealpha($new_image, true);
      }

      // Kopierar en del av bilden till den nya bilden med givna koordinater och storlek
      imagecopy($new_image, $this->image, 0, 0, $x, $y, $this->getWidth(), $this->getHeight());
      $this->image = $new_image;
   }

   /**
    * Ändrar storleken på bilden till den angivna bredden och höjden.
    *
    * @param int $width  Önskad bredd för den nya bilden.
    * @param int $height Önskad höjd för den nya bilden.
    *
    * @throws SystemException Kastar ett undantag om bredd eller höjd är mindre än eller lika med 0.
    */
   function resize($width, $height)
   {
      // Kontrollera att både bredd och höjd är större än 0
      if ($width > 0 && $height > 0) {
         // Skapa en ny bildresurs med den angivna bredden och höjden
         $new_image = imagecreatetruecolor($width, $height);
      } else {
         // Kasta ett undantag om bredd eller höjd är mindre än eller lika med 0
         throw new SystemException(_("Bredd och höjd måste vara större än 0"));
      }

      // Hantera transparenta färger för GIF och PNG-bilder
      if ($this->image_type == IMAGETYPE_GIF || $this->image_type == IMAGETYPE_PNG) {
         $current_transparent = imagecolortransparent($this->image);

         // Konfigurera transparent bakgrund för GIF och PNG
         if ($this->image_type == IMAGETYPE_GIF || $this->image_type == IMAGETYPE_PNG) {
            imagefill($new_image, 0, 0, IMG_COLOR_TRANSPARENT);
            imagealphablending($new_image, false);
            imagesavealpha($new_image, true);
         }

         // Återställ transparent färg om det fanns en i originalbilden
         if ($current_transparent != -1) {
            $transparent_color = imagecolorsforindex($this->image, $current_transparent);
            $current_transparent = imagecolorallocate($new_image, $transparent_color['red'], $transparent_color['green'], $transparent_color['blue']);
            imagefill($new_image, 0, 0, $current_transparent);
            imagecolortransparent($new_image, $current_transparent);
         }
      }

      // Kopiera och ändra storlek på bilden
      imagecopyresampled($new_image, $this->image, 0, 0, 0, 0, $width, $height, $this->getWidth(), $this->getHeight());

      // Uppdatera klassens bild till den nya bilden
      $this->image = $new_image;
   }
}