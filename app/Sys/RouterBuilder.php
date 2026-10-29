<?

/**
 * Class RouterBuilder
 *
 * Handles searching for router comments and constructs .htaccess rules.
 *
 * @category Router
 */
class RouterBuilder
{
    /**
     * @var string Path to the .htaccess file
     */
    public $htaccessPath = "";

    /**
     * @var array List of matched routes
     */
    public $matchedRoutes = [];

    /**
     * @var array List of installations
     */
    public $installList = [];

    /**
     * Constructs the RouterBuilder class.
     *
     * @param string $htaccessPath Path to the .htaccess file.
     * @param string $directory The directory path.
     */
    public function __construct($htaccessPath = '', $directory = '')
    {
        // Set default values if not provided
        $htaccessPath = $htaccessPath ?: $_SERVER["DOCUMENT_ROOT"] . '/.htaccess';
        $directory = $directory ?: $_SERVER['HTTP_path_site'];

        $this->htaccessPath = $htaccessPath;
        $classList = $this->getfiles($directory);
        $this->getComments($classList);
    }
    /**
     * Find class files with router comments in a given directory.
     *
     * @param string $directory The directory path.
     * @return array List of class files with router comments.
     */
    function getfiles($directory)
    {
        $files = new FolderInfoIO($directory);
        return $files->search('\.class\.php', true);
    }

    /**
     * Retrieves comments from class files.
     *
     * @param array $classList List of class files.
     */
    private function getComments($classList = array())
    {
        foreach ($classList as $classFile) {
            $file_content = file_get_contents($classFile->pathName);
            $file_content = mb_convert_encoding($file_content, 'UTF-8', 'AUTO');

            $this->extractRouterComments($file_content, $classFile);
        }
    }

    /**
     * Extracts router comments from the file content.
     *
     * @param string $file_content The content of the file.
     * @param object $classFile The class file object.
     */
    private function extractRouterComments($file_content, $classFile)
    {
        preg_match_all('~@router\s*(?:\{(?<type>.*?)\})?\s*(?<url>[^ ]+)[ ]{0,}\->[ ]{0,}(?<call>.+)~im', $file_content, $matches, PREG_SET_ORDER, 0);

        if (!empty($matches)) {
            $fn = $this->getFunctionNames($file_content, str_replace(".class", "", $classFile->fileName) . 'Controller');
            if ($fn != false) {
                $errors = [];

                foreach ($matches as $match) {
                    $url = $match['url'];
                    $regex_url = preg_replace_callback('/\{([^}]+)\}/', function ($match) {
                        return '(' . $this->buildRegexPattern($match[1]) . ')';
                    }, $url);

                    $httpMethods = isset($match['type']) ? $match['type'] : '';

                    // Validera HTTP-metoder om det inte är tomt
                    if (!empty($httpMethods)) {
                        $validMethods = ['GET', 'POST', 'PUT', 'DELETE', 'OPTIONS', 'PATCH', 'HEAD'];
                        if (!in_array($httpMethods, $validMethods)) {
                            $errors[$url][] = "Ogiltig HTTP-metod för URL: $url";
                            continue;
                        }
                    }

                    // Kolla om $match['call'] matchar det givna regex-mönstret
                    $re = '/^\/m::setup\((\'[a-öA-Ö0-9 -.,*_:$]+\'[,]{0,1}|"[a-öA-Ö0-9 -.,*_:$]+"[,]{0,1}){0,}\)[;]{0,1}$/m';
                    if (preg_match($re, $match['call'])) {
                        // Om det matchar, lägg till i listan
                        $this->installList[] = [
                            'path' => str_replace($_SERVER['HTTP_path_site'], '', $classFile->pathName),
                            'fileName' => str_replace(".class", "", $classFile->fileName),
                            'function' => $match['call'],
                        ];
                    }

                    $this->matchedRoutes[] = [
                        'pageDescription' => 'This for page ' . $url,
                        'regexPattern' => '^' . $this->removeSlashorBackslash($regex_url) . '[/]{0,1}$',
                        'request_method' => $httpMethods,
                        'fileInfo' => [
                            'path' => str_replace($_SERVER['HTTP_path_site'], '', $classFile->pathName),
                            'fileName' => str_replace(".class", "", $classFile->fileName),
                            'function' => $match['call'],
                        ],
                    ];
                }

                if (!empty($errors)) {
                    $cleanedErrors = [];
                    foreach ($errors as $url => $urlErrors) {
                        $cleanedErrors[$url] = array_map('strip_tags', $urlErrors);
                    }
                    throw new SystemException(json_encode($cleanedErrors));
                }
            }
        }
    }

    /**
     * Retrieves function names from a string.
     *
     * @param string $str The string to search in.
     * @param string $targetClass The name of the target class.
     * @return array|bool List of function names or false if class not found.
     */
    function getFunctionNames($str, $targetClass)
    {
        $end = 0;
        $start = 0;
        $x = 0;
        $targetClass = ucfirst($targetClass);

        // Använd regex för att hitta starten av klassen
        if (preg_match('/\bclass\s+' . $targetClass . '\s*(extends IController|extends ApIController){0,1}\s*({)/', $str, $matches, PREG_OFFSET_CAPTURE)) {
            $start = $matches[1][1];
        } else {
            // Returnera om klassen inte hittas
            return false;
        }

        // Iterera från startpositionen
        $x = 0;
        $classCounter = 0;

        // Nu letar du efter '{' och hanterar parenteser
        while ($x < strlen($str)) {
            $x++;

            // Om det är en öppning av parentes {
            if ($str[$x] != "{") {
                // Om det är en öppning av parentes {
                if ($str[$x] != "}") {
                    continue;
                } else {
                    $classCounter--;
                }
            } else {
                $classCounter++;
            }

            if ($classCounter == 0) {
                $end = ($x - $start - 1);
                break;
            }
        }

        $rest = substr($str, $start + 1, $end);

        // Använd regex för att matcha funktioner
        $pattern = '/\bfunction\s+([a-zA-Z_\x7f-\xff][a-zA-Z0-9_\x7f-\xff]*)\s*\(/';
        if (preg_match_all($pattern, $rest, $matches)) {
            // Extrahera resultatet
            return $matches[1];
        } else {
            return false;
        }
    }

    /**
     * Removes slashes or backslashes from a string.
     *
     * @param string $str The string to process.
     * @return string Processed string.
     */
    function removeSlashorBackslash($str)
    {
        // Check if the string only contains "/" or "\"
        if ($str === '/' || $str === '\\') {
            // If so, return an empty string
            return '';
        }
        // Otherwise, return the original string
        return '/(' . $str . ')';
    }

    /**
     * Builds regex pattern for parameters.
     *
     * @param string $param The parameter to build.
     * @return string Regex pattern for the parameter.
     */
    private function buildRegexPattern($param)
    {
        // Split the parameter at ':'
        $parts = explode(':', $param);

        // Extract data type and rules for min and max value
        $dataType = $parts[0];

        if (preg_match('/int\((\d+)-(\d+)\)/', $dataType, $matches)) {
            $min = $matches[1];
            $max = $matches[2];
            return '[0-9,]{' . $min . ',' . $max . '}';
        }

        if (preg_match('/string\((\d+)-(\d+)\)/', $dataType, $matches)) {
            $min = $matches[1];
            $max = $matches[2];
            return '[a-zA-Z0-9-_|,=%!&\'+]{' . $min . ',' . $max . '}';
        }

        // Handle each data type and its rules
        switch ($dataType) {
            case 'string':
                return '[a-zA-Z0-9-_|,=%!&\'+]{1,50}';
            case 'int':
                return '[0-9,]{1,50}';
            default:
                return '[a-zA-Z0-9-_|,=%!&\'+]{1,50}';
        }
    }

    /**
     * Applies rules for the router.
     */

    public function applyRules(){
        // Kolla om det finns data i $this->installList
        if (!empty($this->installList)) {
            // Spara matchedRoutes och installList i session
            session::set('matchedRoutes', $this->matchedRoutes);
            session::set('installList', $this->installList);

            // TODO: Skriv installation vyn
        } else {
            // Hämta senaste kontrolltidpunkt från sessionen
            $lastCheckTime = session::get('lastHtaccessCheck',0);
            
            // Kolla om det har passerat 30 sekunder sedan senaste kontroll
            if (time() - $lastCheckTime > 30) {
                // Kolla när .htaccess senast ändrades
                $lastModifiedTime = filemtime($this->htaccessPath);

                // Kolla om .htaccess har ändrats nyligen (inom de senaste 30 sekunderna)
                if (time() - $lastModifiedTime < 30) {
                    throw new SystemException(_("htaccess har redan uppdaterad med regler några sekunder redan. Det kan vara så att du har skrivit fel funktion i kommentarerna, var vänlig kontrollera."));
                }

                // Spara den nya kontrolltidpunkten i sessionen
                session::set('lastHtaccessCheck', time());
            }

            // Läs .htaccess-filen
            $htaccess = file_get_contents($this->htaccessPath);

            // Hitta platserna för start- och slutkommentarer
            $startComment = '# Start comment';
            $endComment = '# End comment';

            // Hitta start- och slutpositioner för kommentarerna
            $startPosition = strpos($htaccess, $startComment);
            $endPosition = strpos($htaccess, $endComment);

            // Kolla om start- och slutkommentarer finns
            if ($startPosition !== false && $endPosition !== false) {
                // Bygg den del av .htaccess-filen som ska ersättas
                $taget = substr($htaccess, $startPosition, $endPosition - $startPosition + strlen($endComment));

                // Bygg upp den nya datan som ska ersätta den befintliga delen
                $data = $startComment . "\n";

                // Loopa genom $matchedRoutes och lägg till eller uppdatera regler
                foreach ($this->matchedRoutes as $route) {
                    // Bygg upp .htaccess-regel baserat på data i $this->matchedRoutes
                    $data .= "# {$route['pageDescription']}\n";
                    if (!empty($route['request_method'])) {
                        $methods = array_map('trim', explode(',', $route['request_method']));
                        $data .= '<If "';
                        foreach ($methods as $method) {
                            $data .= "%{REQUEST_METHOD} != '$method' || ";
                        }
                        $data = rtrim($data, '|| ');
                        $data .= "\">\n";
                    }
                    $data .= "SetEnvIfNoCase Request_URI \"{$route['regexPattern']}\" HTTP_CONTROLLER={$route['fileInfo']['path']}|{$route['fileInfo']['fileName']}|{$route['fileInfo']['function']}|\n";
                    if (!empty($route['request_method'])) {
                        $data .= "</If>\n";
                    }
                }

                // Lägg till slutkommentaren
                $data .= $endComment;

                // Byt ut den befintliga delen med den uppdaterade
                $htaccess = str_replace($taget, $data, $htaccess);

                // Spara de ändrade .htaccess-reglerna
                file_put_contents($this->htaccessPath, $htaccess);

                // Gör något efter att ha uppdaterat .htaccess (t.ex. omdirigera)
                SiteRouter::wayto($_SERVER['REQUEST_URI']);
            }
        }
    }
}