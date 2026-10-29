<?php 

namespace Sys\SystemException\Handles;

use Sys\SystemException\OutputData;

/**
 * Klass för att hantera utdata och vy-laddning
 *
 * Denna klass tillhandahåller metoder för att hantera data som ska visas i vyer, 
 * kontrollera om en begäran är en Ajax-begäran, och ladda vyer baserat på olika 
 * förhållanden. Klassens funktioner inkluderar även verktyg för att hantera 
 * textutdrag från filer och konvertera minnesstorlekar.
 * @category _("SystemExceptions ValueErrorHandle class")
 */

class ValueErrorHandle extends OutputData
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
    public function view($data)
    {

        $max = 5;
        $end = $data['line'] + $max;

        if (($data['line'] - $max) >= 0) {
            $start = $data['line'] - $max;
        } else {
            $start = 0;
        }

        $row = $start + 1;

        // Kontrollera om utvecklarläge är aktiverat
        if ($_SERVER['HTTP_devmode'] == "true") {
            // Ytterligare logik för utvecklarläge
        }

        $shortCode = $this->Router->ErrorCodeToshortText($data['code']);
        $LongCode = $this->Router->ErrorCodeToLongText($data['code']);

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

        if ($data['_file']) {
            $data['_file'] = ltrim(str_replace(realpath(dirname($_SERVER['HTTP_path_site'])), '', $data['_file']), '/');
        }




        $data['getRegistry'] = $this->Router->getRegistry();
        $this->setData(
            'message',
            $data['parseErrorMessage']['message'],
            'title',
            "SystemException",
            'line',
            $data['parseErrorMessage']['line'],
            'LongCode',
            "Uncaught Value Error",
            'shortCode',
            $data['parseErrorMessage']['shortcode'],
            'where',
            $this->Router::$where,
            'basePath',
            $data['parseErrorMessage']['file'],
            'file',
            $basePath,
            'previous',
            $data['previous'],
            'debug_backtrace_array',
            $data['parseErrorMessage']['stackTrace'],
            'retrieveText',
            $this->retrieveText($data['file'], $start, $end),
            'row',
            $row,
            'getRegistry',
            $this->formattedRegistry($data['getRegistry']),
            'getPostData',
            $this->getPostData()
        );

        if ($this->isAjax()) {
            $msg = array();
            $msg = $data;
            $this->callback($msg);
        } else {
            if ($this->load == false) {
                $this->load('valueErrorHandle.php');
            }
        }
    }

    function parseError($errorMessage)
    {
        $parsedData = [];

        // 1. Ta bort "Uncaught Error:" och "#2 {main} thrown"
        $errorMessage = str_replace('Uncaught Error: ', '', $errorMessage);
        $errorMessage = preg_replace('/#2 \{main\}\s+thrown/', '', $errorMessage);
        // Ta bort root-mappen från sökvägen
        $errorMessage = str_replace($_SERVER['DOCUMENT_ROOT'] . "/", '', $errorMessage);

        // 2. Dela på "Stack trace:"
        $parts = explode('Stack trace:', $errorMessage);

        // Ta bort "Stack trace:"-delen, och spara första delen som 'message'
        $parsedData['message'] = trim($parts[0]);

        $parsedData['message'] = str_replace("php:", 'php on line ', $parsedData['message']);

        // 3. Hantera stack-spårningen och plocka URL, radnummer och funktioner/metoder
        if (isset($parts[1])) {
            $stackTraceLines = explode("\n", trim($parts[1]));
            $stackArray = [];

            foreach ($stackTraceLines as $line) {

                // Regex för att plocka ut filnamn, radnummer och funktion/metod
                if (preg_match('/#\d+ (?<url>.+?)\((?<line>\d+)\): (?<fun>.+)/', $line, $matches)) {
                    $file = $matches['url'];        // Få filnamn och sökväg
                    $lineNumber = $matches['line'];  // Få radnummer
                    $functionCall = $matches['fun']; // Få funktion/metod

                    // Lägg till resultatet i arrayen
                    $stackArray[] = [
                        'stack' => $file . ' ' . $functionCall,
                        'row' => $lineNumber,
                    ];
                }
            }

            $parsedData['stack'] = $stackArray;
        }

        return $parsedData;
    }
}
