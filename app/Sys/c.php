<?php

/**
 * Klass C
 *
 * Denna klass ansvarar för att analysera och logga argumenten, samt presentera dem i en trädstruktur.
 */
class c {
    /**
     * @var array $argument Array som innehåller de analyserade argumenten.
     */
    private $argument = [];
    private $file = "";
    private $line = "";
    private $function = "";

    /**
     * Loggar de givna argumenten och genererar en rapport.
     *
     * Denna metod hämtar alla argument som skickats till funktionen,
     * analyserar dem och visar en rapport över resultaten.
     *
     * @return void
     */
  public static function log() {
    // Hämta alla argument som skickas till log-funktionen
    $arg = func_get_args();

    // Hämta information om var funktionen anropades ifrån
    $backtrace = debug_backtrace();


    // Skapa nytt objekt och analysera argumenten
    $c = new self();
    $c->Analyzer($arg,$backtrace);

    // Skicka rapport
    $c->report();
}

    /**
     * Analyserar de givna argumenten.
     *
     * Itererar över varje argument och sparar dess datatyp samt, om argumentet är ett objekt,
     * objektets klassnamn.
     *
     * @param array $arg Array av argument som ska analyseras.
     * @return void
     */
    private function Analyzer($arg,$backtrace) {
        
            // Exempel: Logga den första posten från backtrace (den som anropade log())
    if (isset($backtrace[0])) {
        $file = $backtrace[0]['file'];  // Filen där log() anropades
        $line = $backtrace[0]['line'];  // Raden där log() anropades
        $function = $backtrace[1]['function'];  // Funktionen som anropade log()

        $this->line = $line;
        $this->file = str_replace($_SERVER['DOCUMENT_ROOT'].'/', '', $file);
        $this->function = $function;
       
    }

        
        foreach ($arg as $index => $value) {
            $dataType = ucfirst(gettype($value));  // Kapitalisera första bokstaven i datatypen
    
            // Bygg det formaterade värdet baserat på datatyp
            if (is_string($value)) {
                $formattedValue = sprintf("String: %s", mb_substr($value, 0, 20));  // Klipp strängen till 20 tecken
            } elseif (is_bool($value)) {
                $formattedValue = sprintf("Bool: %s", $value ? 'Sant' : 'Falskt');  // Hanterar bool
            } elseif (is_int($value)) {
                $formattedValue = sprintf("Nummer: %d", $value);  // Hanterar heltal
            } elseif (is_float($value)) {
                $formattedValue = sprintf("Float: %.2f", $value);  // Hanterar flyttal med två decimaler
            } elseif ($this->isTimeFormat($value)) {
                $formattedValue = sprintf("Time: %s", $value);  // Hanterar tidsformat
            } elseif (is_array($value)) {
                $formattedValue = "Array";  // Hantera arrays genom att konvertera dem till JSON-sträng
            } elseif (is_object($value)) {
                $formattedValue = sprintf("Object: %s", get_class($value));  // Hantera objekt genom att returnera klassnamn
            } else {
                $formattedValue = sprintf("Unknown Type: %s", gettype($value));  // Hantera okända datatyper
            }
    
            // Bygg namnet baserat på index och det formaterade värdet
            $name = sprintf("%d: %s", ($index + 1), $formattedValue);
    
            // Lägg till data i argumentlistan
            $this->argument[] = [
                'name' => $name,
                'datatype' => $dataType,
                'data' => $value  // Lagrar värdet som det är
            ];
        }
    }
    
    // Hjälpmetod för att känna igen tidsformat
    private function isTimeFormat($value) {
        // Kontrollera om värdet är en sträng först, om inte returnera falskt
        if (!is_string($value)) {
            return false;
        }
        
        // Kontrollera om värdet matchar tidsformatet HH:MM:SS
        return preg_match("/^\d{2}:\d{2}:\d{2}$/", $value);
    }


    /**
     * Genererar en rapport över analyserade argument.
     *
     * Denna metod inkluderar en vyfil som visar resultatet av analysen. Om filen inte finns,
     * visas ett felmeddelande.
     *
     * @return void
     */
    private function report() {
        $fullPath = $_SERVER['HTTP_path_sys'] . "Console/Report.php";
        if (file_exists($fullPath)) {
            include $fullPath;
        } else {
            echo "Error: View file not found: $fullPath";
        }
        die();
    }

    /**
     * Skapar en trädvy av ett givet dataset.
     *
     * Denna metod itererar över data och organiserar det i en HTML-trädstruktur, där objekt och arrayer
     * visas som klickbara element.
     *
     * @param mixed $data Datasetet som ska renderas i en trädstruktur.
     * @param string $keyName (valfritt) Namn på nyckeln för det aktuella datasetet.
     * @param bool $isTopLevel (valfritt) Anger om detta är toppnivån i trädstrukturen. Standard är true.
     * @return string HTML-representationen av trädstrukturen.
     */
    private function renderTreeView($data, $keyName = '', $isTopLevel = true) {
        // Om det är ett enkelt värde (inte en array eller ett objekt)
        if (!is_array($data) && !is_object($data)) {
            // Se till att keyName visas korrekt, även om det är tomt
            $label = $keyName !== '' ? htmlspecialchars($keyName) . ': ' : '';
            
            // Format för boolean, nummer, tid, etc.
            if (is_bool($data)) {
                $formattedValue = sprintf("Bool: %s", $data ? 'Sant' : 'Falskt');  // Hanterar bool
            } elseif (is_int($data)) {
                $formattedValue = 'Nummer: ' . $data;
            } elseif (is_float($data)) {
                $formattedValue = 'Float: ' . number_format($data, 2);
            } else {
                // Hantera strängar och andra typer
                $formattedValue = 'String: ' . htmlspecialchars((string)$data);
            }
    
            // Returnera namnet och formaterat värde
            return $label . $formattedValue . '<br>';
        }
    
        // Om det är ett objekt, omvandla det till en array
        if (is_object($data)) {
            $data = (array)$data;
        }
    
        // Börjar bygga trädstrukturen
        $html = $isTopLevel ? '<ul>' : '<ul style="list-style-type:none;white-space: pre;">';
        
        // Om det är på toppnivå och har ett namn, visa det först
        if ($keyName !== '') {
            $html .= '<li><strong>' . htmlspecialchars($keyName) . '</strong></li>';
        }
    
        foreach ($data as $key => $value) {
            // Om värdet är en array eller objekt, gör det klickbart
            if (is_array($value) || is_object($value)) {
                $html .= '<li>';
                $html .= '<span class="collapsible">' . htmlspecialchars($key) . '</span>';  // Klickbart element
                $html .= '<div class="nested">';
                $html .= $this->renderTreeView($value, $key, false);  // Rekursion för att hantera djupare nivåer
                $html .= '</div>';
                $html .= '</li>';
            } else {
                // Om det är ett enkelt värde, visa det direkt
                $html .= '<li>' . htmlspecialchars($key) . ': ' . htmlspecialchars((string)$value) . '</li>';
            }
        }
    
        $html .= '</ul>';
    
        return $html;
    }


}
