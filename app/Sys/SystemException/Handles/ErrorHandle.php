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
 * @category _("SystemExceptions ErrorHandle class")
 */

Class ErrorHandle extends OutputData 
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
    public function view($data) {

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

        $d = $this->parseError($data['message']);
   
        $data['getRegistry'] = $this->Router->getRegistry();
        $this->setData(
            'message',
            $d['message'],
            'title',
            "SystemException",
            'line',
            $data['line'],
            'LongCode',
            "Uncaught Error",
            'shortCode',
            "Oupptäckt fel",
            'where',
            $this->Router::$where,
            'basePath',
            $data['_file'],
            'file',
            $basePath,
            'previous',
            $data['previous'],
            'debug_backtrace_array',
            $d['stack'],
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
                $this->load('typeError.php');
            }
        }       
    }
    
    function parseError($errorMessage)
    {
        $parsedData = [];
    
        // Ta bort "Uncaught Error:" och onödiga delar
        $errorMessage = str_replace('Uncaught Error: ', '', $errorMessage);
        $errorMessage = preg_replace('/#\d+ \{main\}\s+thrown/', '', $errorMessage);
        $errorMessage = str_replace($_SERVER['DOCUMENT_ROOT'].'/', '', $errorMessage);
    
        // Dela på "Stack trace:"
        $parts = explode('Stack trace:', $errorMessage);
        $parsedData['message'] = trim($parts[0]);
    
        $stackArray = [];
    
        if (isset($parts[1])) {
            $lines = explode("\n", trim($parts[1]));
            foreach ($lines as $line) {
                $line = trim($line);
    
                // Matcha vanliga rader: #0 fil(rad): funktion
                if (preg_match('/#\d+\s+(?<file>.+?)\((?<line>\d+)\):\s*(?<func>.+)/', $line, $m)) {
                    $stackArray[] = [
                        'file' => $m['file'],
                        'function' => $m['func'],
                        'line' => $m['line'],
                    ];
                } 
                // Matcha include/require utan radnummer: #1 fil(): include(...)
                elseif (preg_match('/#\d+\s+(?<file>.+?)\(\):\s*(?<func>.+)/', $line, $m)) {
                    $stackArray[] = [
                        'file' => $m['file'],
                        'function' => $m['func'],
                        'line' => 'Radnummer saknas',
                    ];
                }
                // Annars: okänd rad
                else {
                    $stackArray[] = [
                        'file' => null,
                        'function' => $line,
                        'line' => 'Radnummer saknas',
                    ];
                }
            }
        }
    
        $parsedData['stack'] = $stackArray;
    
        return $parsedData;
    }
}