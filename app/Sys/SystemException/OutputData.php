<?php

namespace Sys\SystemException;

/**
 * Klass för att hantera utdata och vy-laddning
 *
 * Denna klass tillhandahåller metoder för att hantera data som ska visas i vyer, 
 * kontrollera om en begäran är en Ajax-begäran, och ladda vyer baserat på olika 
 * förhållanden. Klassens funktioner inkluderar även verktyg för att hantera 
 * textutdrag från filer och konvertera minnesstorlekar.
 * 
 * @category _("SystemExceptions OutputData class")
 */
abstract class OutputData
{
    /**
     * @var array Statisk array för att hålla egenskaper som ska användas i vyer.
     */
    private static $property = array();

    /**
     * @var bool Indikerar om en Ajax-begäran har gjorts.
     */
    private $AjaxHasCall = false;

    /**
     * @var array Data som ska skickas som svar på en Ajax-begäran.
     */
    private $Ajaxdata = array();

    /**
     * @var string Sökvägen till filen som ska laddas för att rendera en vy.
     */
    private $loadfile = "";

    /**
     * @var bool Indikerar om en vy har laddats.
     */
    protected $load = false;

    /**
     * @var bool Skydd mot rekursiv felhantering
     */
    private static $isHandlingError = false;

    /**
     * Kontrollerar om begäran görs via Ajax
     *
     * Kontrollerar om begäran har gjorts via Ajax genom att kontrollera 
     * `HTTP_X_REQUESTED_WITH`-headern eller om begäran är riktad mot en 
     * specifik API-endpoint.
     *
     * @return bool Returnerar true om begäran görs via Ajax, annars false.
     */
    function isAjax()
    {
        $f = false;
        if (
            (isset($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest')
            || (isset($_SERVER['REQUEST_URI']) && strpos($_SERVER['REQUEST_URI'], 'api/js') === 0)
        ) {
            $f = true;
        }
        return $f;
    }

    /**
     * Sätt variabler/värden för att användas i vyer
     *
     * Accepterar ett godtyckligt antal nyckel-värde-par som argument och 
     * lagrar dessa i den statiska egenskapsarrayen. 
     * Om antalet argument är udda kommer ett undantag att kastas.
     *
     * @param mixed ...$args Nyckel-värde-par som argument (t.ex., 'nyckel1', $värde1, 'nyckel2', $värde2, ...)
     * @throws \InvalidArgumentException Om antalet argument är udda.
     * @return $this Returnerar objektet för kedjning.
     */
    final function setData(...$args)
    {
        $numArgs = count($args);

        if ($numArgs % 2 !== 0) {
            throw new \InvalidArgumentException(_('Ojämnt antal argument. Förväntar mig nyckel-värde-par.'));
        }

        for ($i = 0; $i < $numArgs; $i += 2) {
            $name = $args[$i];
            $value = $args[$i + 1];

            self::$property[$name] = $value;
        }

        return $this;
    }

    /**
     * Ladda en vy
     *
     * Laddar en vy baserat på den angivna sökvägen. Om utvecklarläge är aktiverat 
     * används en viss sökväg, annars används en standard 500-felsida. Om ingen 
     * sökväg anges eller om filen inte kan laddas kastas ett undantag.
     *
     * @param string $page Adressen till den vy som ska laddas.
     * @throws \RuntimeException Om ingen sida är specificerad eller om vy-filen inte kan laddas.
     */
    final function load(string $page)
    {
        if (empty($page)) {
            throw new \RuntimeException(sprintf(_('Kunde inte ladda vyn eftersom ingen adress har angetts. %s'), $page));
        }

        // Kontrollera om utvecklarläge är aktiverat
        if (isset($_SERVER['HTTP_devmode']) && $_SERVER['HTTP_devmode'] == "true") {
            $filePath = $_SERVER['HTTP_path_sys'] . 'SystemException/Views/' . $page;
        } else {
            // Ladda 500-fel för icke-utvecklarläge
            $e500 = \config::get('500 page');
            if (file_exists($e500)) {
                $filePath = $e500;
            } else {
                throw new \RuntimeException(sprintf(_('Kunde inte ladda vyn 500'), $page));
            }
        }

        $this->load = true;
        $this->loadfile = $filePath;
    }

    /**
     * Validera om en sträng är en giltig filsökväg
     * 
     * @param string $file Filsökväg att validera
     * @return bool True om det ser ut som en giltig filsökväg
     */
    private function isValidFilePath($file)
    {
        // Kontrollera att det är en sträng
        if (!is_string($file) || empty($file)) {
            return false;
        }

        // Kontrollera om det innehåller tecken som vanligtvis inte finns i filsökvägar
        // men finns i felmeddelanden
        $invalidPatterns = [
            'Invalid',
            'Error',
            'Failed',
            'Warning',
            'Exception',
            'Uncaught',
            'character set',
            'provided',
            'supplied',
        ];

        foreach ($invalidPatterns as $pattern) {
            if (stripos($file, $pattern) !== false) {
                return false;
            }
        }

        // Om filen innehåller mellanslag men inte är en Windows-sökväg med program files
        if (strpos($file, ' ') !== false && 
            stripos($file, 'Program Files') === false &&
            stripos($file, 'C:\\') === false) {
            return false;
        }

        // Kontrollera att det ser ut som en sökväg (innehåller / eller \)
        if (strpos($file, '/') === false && strpos($file, '\\') === false) {
            return false;
        }

        return true;
    }

    /**
     * Hämtar text från en fil mellan specifika rader
     *
     * Läser och returnerar text från en fil mellan de angivna raderna.
     * VIKTIGT: Denna metod har rekursionsskydd för att förhindra oändliga loopar.
     *
     * @param string $file Filens sökväg.
     * @param int $min Lägsta radnummer.
     * @param int $max Högsta radnummer.
     * @return array En array av text mellan de specificerade raderna, eller tom array vid fel.
     */
    function retrieveText($file, $min, $max)
    {
        // KRITISKT: Förhindra rekursiv felhantering
        if (self::$isHandlingError) {
            error_log("OutputData::retrieveText - Rekursiv felhantering upptäckt, avbryter");
            return array();
        }

        // Validera att $file är en giltig filsökväg
        if (!$this->isValidFilePath($file)) {
            error_log("OutputData::retrieveText - Ogiltig filsökväg (troligen ett felmeddelande): " . substr($file, 0, 100));
            return array();
        }

        // Kontrollera att filen existerar
        if (!file_exists($file)) {
            error_log("OutputData::retrieveText - Filen existerar inte: {$file}");
            return array();
        }

        // Kontrollera att det är en fil (inte katalog)
        if (!is_file($file)) {
            error_log("OutputData::retrieveText - Inte en fil: {$file}");
            return array();
        }

        // Kontrollera att filen är läsbar
        if (!is_readable($file)) {
            error_log("OutputData::retrieveText - Filen är inte läsbar: {$file}");
            return array();
        }

        // Sätt rekursionsskydd
        self::$isHandlingError = true;

        try {
            $output = array();
            $line = -1;
            
            // Använd @ för att förhindra att fopen skapar en warning som triggar error handler
            $handle = @fopen($file, 'r');

            if ($handle === false) {
                error_log("OutputData::retrieveText - Kunde inte öppna fil: {$file}");
                self::$isHandlingError = false;
                return array();
            }

            while (!feof($handle)) {
                $line++;
                if (($line >= $min && $line <= $max)) {
                    $output[] = fgets($handle);
                } elseif ($line > $max) {
                    break;
                } else {
                    fgets($handle);
                }
            }
            
            fclose($handle);
            
            // Återställ rekursionsskydd
            self::$isHandlingError = false;
            
            return $output;

        } catch (\Throwable $e) {
            // Fånga alla fel utan att skapa ny exception
            error_log("OutputData::retrieveText - Exception: " . $e->getMessage());
            self::$isHandlingError = false;
            return array();
        }
    }

    /**
     * Formaterar meddelanden för visning i en textarea
     *
     * Tar emot ett eller flera meddelanden och formaterar dem för visning i en textarea.
     *
     * @param mixed $messages Ett eller flera meddelanden som ska formateras.
     * @return array En array med formaterade meddelanden.
     */
    function textArea($messages)
    {
        $tmp = array();
        $i = 0;
        if (is_array($messages)) {
            foreach ($messages as $m) {
                if($i === 0){
                    $tmp[] = $m ;
                    $i++;
                }else{
                    $tmp[] = '<div class="p" style="margin-top: 15px;">' . $m . '</div>';
                }
                
            }
        } else {
            $tmp[0] = '<span class="erorr-type">' . $messages . '</span>';
        }

        return $tmp;
    }

    /**
     * Hämtar POST-data för diagnostikändamål
     *
     * Samlar in olika HTTP-headers och systeminformation för diagnostikändamål.
     *
     * @return array En array med diagnostikinformation.
     */
    function getPostData()
    {
        $loop[_('Domännamnet')] = $_SERVER['HTTP_HOST'] ?? 'unknown';
        $loop[_('Datum')] = date("Y-m-d h:i:s", $_SERVER['REQUEST_TIME'] ?? time());
        $loop[_('Tid')] = isset($_SERVER["REQUEST_TIME_FLOAT"]) ? round(microtime(true) - $_SERVER["REQUEST_TIME_FLOAT"], 4) : 0;
        $loop[_('System tar på minnet')] = $this->convert(memory_get_usage());
        $loop[_('PHP ta på minne')] = $this->convert(memory_get_peak_usage());

        if (array_key_exists('HTTP_REFERER', $_SERVER)) {
            $loop[_('Detta är adressen till föregående webbsida')] = $_SERVER['HTTP_REFERER'];
        }

        $loop[_('Änvändaragenten')] = $_SERVER['HTTP_USER_AGENT'] ?? 'unknown';
        $loop[_('Acceptabla')] = $_SERVER['HTTP_ACCEPT'] ?? '*/*';
        $loop[_('Godtagbara kodningar')] = $_SERVER['HTTP_ACCEPT_ENCODING'] ?? 'none';
        $loop[_('Anslutningen')] = $_SERVER['HTTP_CONNECTION'] ?? 'unknown';

        return $loop;
    }

    /**
     * Destruktor för att hantera utmatning och laddning av vyn
     *
     * Hanterar utmatning av data när objektet destrueras, inklusive att skicka 
     * JSON-svar för Ajax-begäran eller att inkludera en vyfil.
     */
    function __destruct()
    {

        if ($this->AjaxHasCall) {
            header('Content-type:application/json; charset=utf-8');
            die(json_encode($this->Ajaxdata));
        }

        $path = $this->loadfile;
        $property = self::$property;

        if (!empty($path)) {
            while (ob_get_level()) {
                $handlers = ob_list_handlers();
                $current_handler = end($handlers);
                
                if ($current_handler !== 'ob_gzhandler') {
                    ob_end_clean();
                } else {
                    break; // Avsluta loopen om ob_gzhandler är aktiv
                }
            }
            
            ob_start();

            if (!empty($property)) {
                foreach ($property as $key => $value) {
                    $$key = $value;
                }
            }

            if (file_exists($path)) {
                include($path);
            } else {
                throw new \RuntimeException(sprintf(_('Kunde inte ladda den här vyn. %s'), $path));
            }

            $content = ob_get_clean();
            echo $content;
        }
    }

    /**
     * Konverterar en minnesstorlek till en läsbar sträng
     *
     * Konverterar minnesstorlek till en läsbar form, t.ex. bytes, kilobytes, megabytes.
     *
     * @param int $size Storleken i bytes.
     * @return string En läsbar form av storleken.
     */
    private function convert($size)
    {
        $unit = array('B', 'KB', 'MB', 'GB', 'TB', 'PB', 'EB', 'ZB', 'YB');
        $power = $size > 0 ? floor(log($size, 1024)) : 0;
        return number_format($size / pow(1024, $power), 2) . ' ' . $unit[$power];
    }

    /**
     * Formaterar en array av filregisterposter genom att översätta PHP-filtypkategorier.
     *
     * Denna funktion bearbetar en array där varje element representerar en filpost med 'type' och 'time'.
     * Den kontrollerar om filtypen är PHP, extraherar och översätter kategoristrängen om den finns, och
     * formaterar utdata därefter. Om filtypen inte är PHP eller om översättning inte är tillgänglig,
     * behålls den ursprungliga typsträngen.
     *
     * @param array $registry En associativ array där varje element är en array med följande nycklar:
     *                        - 'type' (string): Sökvägen eller typen av filen.
     *                        - 'time' (blandat): Ett tidsstämpel eller tidsrelaterad information.
     *
     * @return array Returnerar en ny array med samma struktur som indata, där:
     *               - 'type' fältet är antingen den översatta kategoristrängen (om filen är PHP och översättning är tillgänglig),
     *                 eller den ursprungliga filtypsträngen (om inte).
     *               - 'time' fältet är bevarat från indata.
     *
     * @throws Exception Om det uppstår ett problem med att läsa en fil eller om filtypen inte är en giltig PHP-fil.
     */
    function formattedRegistry(array $registry)
    {
        $formattedRegistry = array();

        foreach ($registry as $key => $file) {
            // Kontrollera om filtypen är PHP
            if (strpos($file['type'], '.php') !== false) {
                // Läs in filen
                $content = @file_get_contents($file['type']);

                if ($content === false) {
                    $formattedRegistry[$key]['type'] = str_replace($_SERVER['DOCUMENT_ROOT'] . DIRECTORY_SEPARATOR, "", $file['type']);
                    $formattedRegistry[$key]['time'] = $file['time'];
                    continue;
                }

                $re = '/@category\s*(.*)/';

                if (preg_match($re, $content, $matches)) {
                    $category_string = trim($matches[1]);

                    // Ta bort _(" från början av strängen
                    if (substr($category_string, 0, 3) === '_("') {
                        $category_string = substr($category_string, 3); // Ta bort de första 3 tecknen (inklusive citat)
                    }

                    // Ta bort ") från slutet av strängen
                    if (substr($category_string, -2) === '")') {
                        $category_string = substr($category_string, 0, -2); // Ta bort de sista 2 tecknen (inklusive citat)
                    }

                    // Översätt strängen med gettext
                    if (function_exists('_')) {
                        $translated_string = _($category_string);
                        $formattedRegistry[$key]['type'] = $translated_string;
                    } else {
                        // Om gettext inte är tillgänglig, använd den ursprungliga strängen
                        $formattedRegistry[$key]['type'] = $category_string;
                    }
                } else {
                    // Om ingen kategori hittades, behåll ursprunglig typ och tid
                    $formattedRegistry[$key]['type'] = str_replace($_SERVER['DOCUMENT_ROOT'] . DIRECTORY_SEPARATOR, "", $file['type']);
                }
            } else {
                // Om filen inte är av typen PHP, behåll ursprunglig typ och tid
                $formattedRegistry[$key]['type'] = str_replace($_SERVER['DOCUMENT_ROOT'] . DIRECTORY_SEPARATOR, "", $file['type']);
            }
            // Spara tiden
            $formattedRegistry[$key]['time'] = $file['time'];
        }

        return $formattedRegistry;
    }

    /**
     * Rendrerar en vy i json format
     *
     * Första argumentet är data
     *
     * @param mixed $data Värde som ska konverteras till JSON-format och skickas till klienten.
     */
    final function callback($data)
    {

        $this->AjaxHasCall = true;
        $this->Ajaxdata = $data;
    }

    /**
     * Extraherar information om en funktion från en fil baserat på en angiven rad.
     *
     * Denna metod letar efter en funktionsdeklaration vid eller före den angivna raden,
     * och returnerar information om funktionsnamn, argument och PHPDoc-kommentaren (om den finns).
     * Om ingen funktion hittas returneras en tom array.
     *
     * @param string $file Sökvägen till filen där funktionen finns.
     * @param int $line Linjenummer där funktionen är definierad eller nära definierad.
     * @return array En array med följande nycklar:
     *               - 'function' (string): Namnet på funktionen.
     *               - 'arguments' (array): En lista över funktionsargument.
     *               - 'phpdoc' (string|null): PHPDoc-kommentaren som hör till funktionen, eller null om ingen kommentar finns.
     */
    protected function getFunctionFromFile($file, $line)
    {
        $result = [];
        
        // Validera filsökväg först
        if (!$this->isValidFilePath($file) || !file_exists($file)) {
            return $result;
        }

        $lines = @file($file);
        
        if ($lines === false) {
            return $result;
        }

        $numLines = count($lines);

        if ($line > $numLines) {
            return $result;  // Returnera tom array om linjen är utanför filens gränser
        }

        // Leta efter funktionsdeklarationen genom att leta bakåt från den angivna raden
        for ($i = $line - 1; $i >= 0; $i--) {
            $lineContent = trim($lines[$i]);

            // Kontrollera om vi har hittat en funktionsdeklaration
            if (strpos($lineContent, 'function ') !== false) {
                $functionName = '';
                $arguments = '';
                $openParenthesisCount = 0;
                $closeParenthesisCount = 0;

                // Hitta funktionsnamn och argumenten genom att gå framåt
                for ($j = $i; $j < $numLines; $j++) {
                    $lineContent = trim($lines[$j]);

                    // Kolla om vi har hittat funktionsnamnet och argumenten
                    if (preg_match('/function\s+(\w+)/', $lineContent, $matches)) {
                        $functionName = $matches[1];
                    }

                    // Samla ihop argumenten tills vi når slutet av funktionsdeklarationen
                    $openParenthesisCount += substr_count($lineContent, '(');
                    $closeParenthesisCount += substr_count($lineContent, ')');
                    $arguments .= $lineContent;

                    if ($openParenthesisCount > 0 && $openParenthesisCount === $closeParenthesisCount) {
                        // Sluta samla när vi har hittat den stängande parentesen
                        break;
                    }
                }

                // Extrahera argumenten från funktionsdeklarationen
                $arguments = trim(preg_replace('/function\s+\w+\s*\((.*)\)/', '$1', $arguments));
                $argumentsArray = array_map('trim', explode(',', $arguments));

                // Extrahera PHPDoc-kommentaren om den finns precis ovanför funktionen
                $phpDoc = [];
                $foundPhpDocStart = false;

                for ($j = $i - 1; $j >= 0; $j--) {
                    $commentLine = trim($lines[$j]);

                    if (strpos($commentLine, '*/') !== false) {
                        // Hittade slutet på PHPDoc, nu letar vi bakåt tills vi hittar starten (/**)
                        for ($k = $j; $k >= 0; $k--) {
                            $docLine = trim($lines[$k]);
                            $phpDoc[] = $docLine;

                            if (strpos($docLine, '/**') !== false) {
                                $foundPhpDocStart = true;
                                break;  // Avsluta när början på PHPDoc hittas
                            }
                        }

                        if ($foundPhpDocStart) {
                            $phpDoc = array_reverse($phpDoc); // Invertera kommentarlinjerna för rätt ordning
                            break;
                        } else {
                            $phpDoc = [];  // Om vi inte hittade början, rensa PHPDoc
                            break;
                        }
                    }
                }

                // Skapa array med funktionsnamn, argument och PHPDoc (om den finns)
                $result = [
                    'function' => $functionName,
                    'arguments' => !empty($argumentsArray) ? $argumentsArray : [],  // Dela upp argument om de finns
                    'phpdoc' => !empty($phpDoc) ? implode("\n", $phpDoc) : null
                ];
                break;  // Avsluta loopen när vi hittat funktionen
            }
        }

        return $result;  // Returnera tom array om ingen funktion hittas
    }
}