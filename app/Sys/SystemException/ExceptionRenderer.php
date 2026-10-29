<?php

namespace Sys\SystemException;

/**
 * ExceptionRenderer
 * 
 * Hanterar rendering av exceptions för både CLI och web-miljöer.
 * Separerar presentation-logik från felhantering.
 * 
 * @category Exception Rendering
 */
class ExceptionRenderer
{
    private array $data = [];
    private bool $isCli;
    private string $viewPath;
    private static ?ExceptionRenderer $instance = null;

    /**
     * Konstruktor
     * 
     * @param array $data Data som ska renderas
     * @param string|null $viewPath Sökväg till view-filer (används endast för web)
     */
    public function __construct(array $data = [], ?string $viewPath = null)
    {
        $this->data = $data;
        $this->isCli = php_sapi_name() === 'cli';
        $this->viewPath = $viewPath ?? dirname(__DIR__) . '/SystemException/Views/';
    }

    /**
     * Sätt data som ska renderas
     * 
     * @param array $data
     * @return self
     */
    public function setData(array $data): self
    {
        $this->data = array_merge($this->data, $data);
        return $this;
    }

    /**
     * Hämta data
     * 
     * @return array
     */
    public function getData(): array
    {
        return $this->data;
    }

    /**
     * Rendera header
     * 
     * @return self För method chaining
     */
    public function header(): self
    {
        $this->renderHeader();
        return $this;
    }

    /**
     * Rendera body/view
     * 
     * @return self För method chaining
     */
    public function view(): self
    {
        if ($this->isCli) {
            $this->renderCliBody();
        } else {
            $this->renderWebBody();
        }
        return $this;
    }

    /**
     * Rendera footer
     * 
     * @return self För method chaining
     */
    public function footer(): self
    {
        $this->renderFooter();
        return $this;
    }

    /**
     * Rendera header (intern metod)
     * 
     * @return void
     */
    private function renderHeader(): void
    {
        if ($this->isCli) {
            $this->renderCliHeader();
        } else {
            $this->renderWebHeader();
        }
    }

    /**
     * Rendera footer (intern metod)
     * 
     * @return void
     */
    private function renderFooter(): void
    {
        if ($this->isCli) {
            $this->renderCliFooter();
        } else {
            $this->renderWebFooter();
        }
    }

    /**
     * Rendera komplett felmeddelande
     * 
     * @return void
     */
    public function render(): void
    {
        $this->header()->view()->footer();
    }

    /**
     * Rendera CLI header
     * 
     * @return void
     */
    private function renderCliHeader(): void
    {
        echo "\n";
        echo str_repeat("=", 80) . "\n";
        echo "  SYSTEMFEL\n";
        echo str_repeat("=", 80) . "\n";
        echo "\n";
    }

    /**
     * Rendera CLI footer
     * 
     * @return void
     */
    private function renderCliFooter(): void
    {
        echo "\n";
        echo str_repeat("=", 80) . "\n";
        echo "\n";
    }

    /**
     * Rendera CLI body
     * 
     * @return void
     */
    private function renderCliBody(): void
    {
        // Titel
        if (!empty($this->data['title'])) {
            echo "Typ: " . $this->data['title'] . "\n";
        }

        // Meddelande
        if (!empty($this->data['message'])) {
            echo "\nMeddelande:\n";
            if (is_array($this->data['message'])) {
                foreach ($this->data['message'] as $msg) {
                    echo "  - " . $msg . "\n";
                }
            } else {
                echo "  " . $this->data['message'] . "\n";
            }
        }

        // Felkod
        if (!empty($this->data['code'])) {
            echo "\nFelkod: " . $this->data['code'] . "\n";
        }

        // Fil och rad
        if (!empty($this->data['file'])) {
            echo "\nFil: " . $this->data['file'];
            if (!empty($this->data['line'])) {
                echo ":" . $this->data['line'];
            }
            echo "\n";
        }

        // Parsed error details
        if (!empty($this->data['parseErrorMessage'])) {
            $parsed = $this->data['parseErrorMessage'];
            
            if (!empty($parsed['errorType'])) {
                echo "\nFeltyp: " . $parsed['errorType'] . "\n";
            }
            
            if (!empty($parsed['shortcode'])) {
                echo "Översättning: " . $parsed['shortcode'] . "\n";
            }

            // Stack trace
            if (!empty($parsed['stackTrace'])) {
                echo "\nStack trace:\n";
                foreach ($parsed['stackTrace'] as $i => $trace) {
                    echo "  #{$i} " . $trace['file'] . ":" . $trace['line'] . "\n";
                    echo "      " . $trace['function'] . "(" . $trace['args'] . ")\n";
                }
            }
        }

        // Debug backtrace (om tillgänglig)
        if (!empty($this->data['debug']) && is_array($this->data['debug'])) {
            echo "\nDebug backtrace:\n";
            foreach ($this->data['debug'] as $i => $trace) {
                if (isset($trace['file'])) {
                    echo "  #{$i} " . basename($trace['file']);
                    if (isset($trace['line'])) {
                        echo ":" . $trace['line'];
                    }
                    if (isset($trace['function'])) {
                        echo " - " . $trace['function'] . "()";
                    }
                    echo "\n";
                }
            }
        }
    }

    /**
     * Rendera web header
     * 
     * @return void
     */
    private function renderWebHeader(): void
    {
        $file = $this->viewPath . 'include/header.php';
        
        if (!is_file($file)) {
            error_log("SystemException header view saknas: {$file}");
            echo '<!doctype html>
<html lang="sv">
<head>
    <meta charset="utf-8">
    <title>Systemfel</title>
    <style>
        body { font-family: Arial, sans-serif; margin: 40px; background: #f5f5f5; }
        .error-container { background: white; padding: 30px; border-radius: 8px; box-shadow: 0 2px 10px rgba(0,0,0,0.1); }
        h1 { color: #d32f2f; margin-top: 0; }
        .error-details { background: #fff3cd; padding: 15px; border-left: 4px solid #ffc107; margin: 20px 0; }
        code { background: #f5f5f5; padding: 2px 6px; border-radius: 3px; }
        pre { background: #f5f5f5; padding: 15px; overflow-x: auto; border-radius: 4px; }
    </style>
</head>
<body>
<div class="error-container">
<h1>Allvarligt systemfel</h1>';
            return;
        }
        
        $this->requireViewFile($file);
    }

    /**
     * Rendera web footer
     * 
     * @return void
     */
    private function renderWebFooter(): void
    {
        $file = $this->viewPath . 'include/footer.php';
        
        if (!is_file($file)) {
            error_log("SystemException footer view saknas: {$file}");
            echo '</div></body></html>';
            return;
        }
        
        $this->requireViewFile($file);
    }

    /**
     * Rendera web body
     * 
     * @return void
     */
    private function renderWebBody(): void
    {
        // Titel
        if (!empty($this->data['title'])) {
            echo '<h2>' . htmlspecialchars($this->data['title']) . '</h2>';
        }

        // Meddelande
        if (!empty($this->data['message'])) {
            echo '<div class="error-details">';
            echo '<strong>Meddelande:</strong><br>';
            if (is_array($this->data['message'])) {
                echo '<ul>';
                foreach ($this->data['message'] as $msg) {
                    echo '<li>' . htmlspecialchars($msg) . '</li>';
                }
                echo '</ul>';
            } else {
                echo '<p>' . htmlspecialchars($this->data['message']) . '</p>';
            }
            echo '</div>';
        }

        // Fil och rad
        if (!empty($this->data['file'])) {
            echo '<p><strong>Fil:</strong> <code>' . htmlspecialchars($this->data['file']) . '</code>';
            if (!empty($this->data['line'])) {
                echo ' <strong>Rad:</strong> <code>' . htmlspecialchars($this->data['line']) . '</code>';
            }
            echo '</p>';
        }

        // Felkod
        if (!empty($this->data['code'])) {
            echo '<p><strong>Felkod:</strong> <code>' . htmlspecialchars($this->data['code']) . '</code></p>';
        }

        // Parsed error
        if (!empty($this->data['parseErrorMessage'])) {
            $parsed = $this->data['parseErrorMessage'];
            echo '<div class="error-details">';
            
            if (!empty($parsed['errorType'])) {
                echo '<p><strong>Feltyp:</strong> ' . htmlspecialchars($parsed['errorType']) . '</p>';
            }
            
            if (!empty($parsed['shortcode'])) {
                echo '<p><strong>Beskrivning:</strong> ' . htmlspecialchars($parsed['shortcode']) . '</p>';
            }

            // Stack trace
            if (!empty($parsed['stackTrace'])) {
                echo '<h3>Stack Trace</h3>';
                echo '<pre>';
                foreach ($parsed['stackTrace'] as $i => $trace) {
                    echo "#$i " . htmlspecialchars($trace['file']) . ":" . htmlspecialchars($trace['line']) . "\n";
                    echo "   " . htmlspecialchars($trace['function']) . "(" . htmlspecialchars($trace['args']) . ")\n\n";
                }
                echo '</pre>';
            }
            
            echo '</div>';
        }
    }

    /**
     * Inkludera view-fil med isolerad scope
     * 
     * @param string $file
     * @return void
     */
    private function requireViewFile(string $file): void
    {
        $__file = $file ?? ''; // fallback to empty string
        $__data = $this->data ?? [];

        // Skapa en anonym funktion för att isolera scope
        $renderer = function($__file, $__data) {
            if (empty($__file) || !file_exists($__file)) {
                throw new \ValueError("View file path cannot be empty or missing: {$__file}");
            }

            extract($__data, EXTR_SKIP);
            require $__file; 
            unset($__file, $__data); // unset after use
        };

        // Anropa med filen och data
        $renderer($__file, $__data);
    }
    
    /**
     * Statisk factory method för att skapa renderer
     * 
     * @param array $data
     * @param string|null $viewPath
     * @return self
     */
    public static function create(array $data = [], ?string $viewPath = null): self
    {
        return new self($data, $viewPath);
    }

    /**
     * Kontrollera om vi kör i CLI-läge
     * 
     * @return bool
     */
    public function isCli(): bool
    {
        return $this->isCli;
    }

    /**
     * Sätt CLI-läge manuellt (för testning)
     * 
     * @param bool $isCli
     * @return self
     */
    public function setCliMode(bool $isCli): self
    {
        $this->isCli = $isCli;
        return $this;
    }
}
