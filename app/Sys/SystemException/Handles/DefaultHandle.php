<?
namespace Sys\SystemException\Handles;
use Sys\SystemException\OutputData;

/**
 * DefaultHandler-klassen hanterar undantag relaterade till ett felaktigt antal argument vid anrop av en funktion eller metod.
 * Den ärver från OutputData och tillhandahåller funktionalitet för att analysera felmeddelanden, extrahera information om
 * filvägar, radnummer och andra användbara diagnostiska data.
 * 
 * @category _("SystemExceptions DefaultHandler class")
 * @package Sys\SystemException\Handles
 */
Class DefaultHandle extends OutputData 
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

         if(is_array($data['message'])){
            $this->setData(
                'LongCode',
                "",
                'shortCode',
                "",
            );
         }else{
            $this->setData(
                'LongCode',
                $LongCode,
                'shortCode',
                $shortCode,
            );
         }   

        $data['getRegistry'] = $this->Router->getRegistry();
        $this->setData(
            'messages',
            $this->textArea($data['message']),
            'title',
            $data['title'],
            'line',
            $data['line'],
            'where',
            $this->Router::$where,
            'basePath',
            $data['_file'],
            'file',
            $basePath,
            'previous',
            $data['previous'],
            'debug_backtrace_array',
            $this->Router->debug_backtrace_array(),
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
                $this->load('default.php');
            }
        }
    }
}