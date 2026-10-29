<?php 


/**
 * Class Bb
 * A class for converting BB code to HTML and plain text
 * 
 * @category _("Text Processing klass")
 */
class Bb
{

    private $strInput;

    /**
     * Constructor
     * 
     * @param string $strInput The input string containing BB code
     */
    function __construct($strInput)
    {
        $this->strInput = $strInput;
    }

    /**
     * Convert BB code to HTML
     * 
     * @return string The HTML result
     */
    function code2html()
    {
        return preg_replace(
            array(
                '/\\[url[\\:\\=]((\\"([\\W]*javascript\:[^\\"]*)?([^\\"]*)\\")|' .
                    '(([\\W]*javascript\:[^\\]]*)?([^\\]]*)))\\]/ie', '/\\[\\/url\\]/i',
                '/\\[b\\]/i', '/\\[\/b\\]/i',
                '/\\[i\\]/i', '/\\[\/i\\]/i',
                '/\\[u\\]/i', '/\\[\/u\\]/i',
                '/\\[s\\]/i', '/\\[\/s\\]/i',
                '/\\[quote\\]/i', '/\\[\/quote\\]/i'
            ),
            array(
                '\'<a href="\'.(\'$4\'?\'$4\':\'$7\').\'">\'', '</a>',
                '<b>', '</b>',
                '<i>', '</i>',
                '<u>', '</u>',
                '<s>', '</s>',
                '<blockquote>', '</blockquote>'
            ),
            $this->strInput
        );
    }

    /**
     * Convert BB code to plain text
     * 
     * @return string The plain text result
     */
    function code2text()
    {
        return preg_replace(
            array(
                '/\\[url[\\:\\=]((\\"([\\W]*javascript\:[^\\"]*)?([^\\"]*)\\")|' .
                    '(([\\W]*javascript\:[^\\]]*)?([^\\]]*)))\\]/ie', '/\\[\\/url\\]/i',
                '/\\[b\\]/i', '/\\[\/b\\]/i',
                '/\\[i\\]/i', '/\\[\/i\\]/i',
                '/\\[u\\]/i', '/\\[\/u\\]/i',
                '/\\[s\\]/i', '/\\[\/s\\]/i',
                '/\\[quote\\]/i', '/\\[\/quote\\]/i'
            ),
            array(
                '', '',
                '', '',
                '', '',
                '', '',
                '', '',
                '', ''
            ),
            $this->strInput
        );
    }
}
