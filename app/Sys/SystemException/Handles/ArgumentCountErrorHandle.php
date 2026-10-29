<?php 
namespace Sys\SystemException\Handles;
use Sys\SystemException\OutputData;

/**
 * ArgumentCountError-klassen hanterar undantag relaterade till ett felaktigt antal argument vid anrop av en funktion eller metod.
 * Den ärver från OutputData och tillhandahåller funktionalitet för att analysera felmeddelanden, extrahera information om
 * filvägar, radnummer och andra användbara diagnostiska data.
 * 
 * @category _("SystemExceptions ArgumentCountError class")
 * @package Sys\SystemException\Handles
 */
class ArgumentCountErrorHandle extends OutputData
{
    /**
     * @var object $Router Objekt som används för att hantera routning och diagnoser.
     */
    protected $Router;

    /**
     * Konstruktor.
     * 
     * @param object $object Ett objekt som hanterar routning.
     * @param array $data Array med felmeddelande och annan diagnostisk information.
     */
    public function __construct($object, array $data = array())
    {
        $this->Router = $object;
        $this->view($data);
    }

    /**
     * Visar felinformationen genom att analysera felmeddelandet och extrahera data som filväg, radnummer och funktioner.
     * Ställer också upp data för visning eller loggning beroende på om det är en vanlig förfrågan eller en AJAX-förfrågan.
     * 
     * @param array $data Array som innehåller information om felmeddelandet och relaterade data.
     * 
     * @return void
     */
    public function view($data){
        // Här är regex-mönstret för att matcha filväg och radnummer från $data['message']
        $patternCall = '/\/[^ ]+\.php on line \d+/';

        // Hitta alla matchningar i meddelandet för varje fil och radnummer
        preg_match_all($patternCall, $data['message'], $matchesCall);

        if (!empty($matchesCall[0])) {
            // Extrahera första matchningen för var felet inträffade
            $callLocation = $matchesCall[0][0];  // T.ex. "/customers/.../site/user/user.class.php on line 200"
            $callFile = substr($callLocation, 0, strrpos($callLocation, ' on line'));  // Filvägen
            $callLine = substr($callLocation, strrpos($callLocation, ' ') + 1);  // Radnumret
        } else {
            // Om regex inte hittar något, hantera som okänt
            $callFile = "Unknown file";
            $callLine = "Unknown line";
        }

        // Hitta var anropet vidare skickades som orsakade felet (andra filen och raden)
        $patternError = '/in (\/[^ ]+\.php):(\d+)/';
        preg_match($patternError, $data['message'], $matchesError);

        if (!empty($matchesError)) {
            $errorFile = $matchesError[1];  // Filen där felet inträffade
            $errorLine = $matchesError[2];  // Radnumret där felet inträffade
        } else {
            $errorFile = "Unknown file";
            $errorLine = "Unknown line";
        }

        $max = 5;

        // Klipper ut data från där felet inträffade
        $endCallLine = $callLine + $max;

        if (($callLine - $max) >= 0) {
            $startCallLine = $callLine - $max;
        } else {
            $startCallLine = 0;
        }

        $rowCall = $startCallLine + 1;

        // Klipper ut data från felkällan
        $endLine = $errorLine + $max;

        if (($errorLine - $max) >= 0) {
            $startLine = $errorLine - $max;
        } else {
            $startLine = 0;
        }

        $row = $startLine + 1;

        // Kontrollera om utvecklarläge är aktiverat
        if ($_SERVER['HTTP_devmode'] == "true") {
            // Ytterligare logik för utvecklarläge kan läggas här
        }

        // Hämta sökvägen från $_SERVER-variabeln
        $path = $_SERVER['HTTP_path_site'];

        // Dela sökvägen efter varje '/' och ta den sista delen
        $parts = explode('/', rtrim($path, '/'));
        $lastPart = end($parts);

        // Ta bort den sista delen från vänster
        $basePath = $lastPart;
        if (($pos = strpos($data['file'], $lastPart)) !== false) {
            $basePath .= substr($data['file'], $pos + strlen($lastPart));
        }

        // Justerar filsökvägen om den finns
        if (isset($data['_file'])) {
            $data['_file'] = ltrim(str_replace(realpath(dirname($_SERVER['HTTP_path_site'])), '', $data['_file']), '/');
        }

        // Sätt upp data för visning eller loggning
        $this->setData(
            'messages',
            $this->textArea(str_replace("Uncaught ArgumentCountError:", "", $data['message'])),
            'title',
            "SystemException",
            'line',
            $errorLine,
            'callLine',
            $callLine,
            'LongCode',
            "Uncaught ArgumentCountError",
            'shortCode',
            _("Oupptäckt argumenträkningsfel"),
            'where',
            $this->Router::$where,
            'basePath',
            $data['_file'],
            'file',
            $basePath,
            'callFile',
            str_replace($_SERVER["DOCUMENT_ROOT"], '', $callFile),
            'previous',
            $data['previous'],
            'debug_backtrace_array',
            $this->Router->debug_backtrace_array(),
            'retrieveText',
            $this->retrieveText($errorFile, $startLine, $endLine),
            'retrieveTextCall',
            $this->retrieveText($callFile, $startCallLine, $endCallLine),
            'row',
            $row,
            'rowCall',
            $rowCall,
            'getRegistry',
            $this->formattedRegistry($this->Router->getRegistry()),
            'getPostData',
            $this->getPostData(),
            'functionInfo',
            $this->getFunctionFromFile($errorFile, $errorLine)
        );

        // Ta bort 'debug' från data-arrayen
        unset($data['debug']);

        // Kontrollera om det är en AJAX-förfrågan
        if ($this->isAjax()) {
            $msg = $data;
            $this->callback($msg);
        } else {
            if ($this->load == false) {
                $this->load('argumentCountError.php');
            }
        }
    }
}