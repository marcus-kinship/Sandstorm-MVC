<?php

namespace Sys\DatabaseConnection;

use \SystemException;

/**
 * Database query error handling.
 *
 * Handles PHP function errors that usually do not have exceptions. Converts
 * common error messages to exceptions where possible.
 *
 * @category _("Database Exception class")
 */
class DatabaseException extends SystemException
{
    private $errorArguments = "";
    private $data = array();

    /**
     * Constructor for DatabaseException.
     *
     * @param string         $message  The error message.
     * @param array          $query    The database query context.
     * @param int            $code     The error code.
     * @param Throwable|null $previous Previous exception.
     */
    public function __construct($message, $query = array(), $code = 0, Throwable $previous = null)
    {
        $f = debug_backtrace();
        $line = $f[2]['line'];
        $file = $f[2]['file'];
        unset($f);

        $this->setTitle("DatabaseException");

        $errorComponents = [];
        $errorComponents[
            "<span>" . _("SQL Query") . '</span><span>' . _("Type") . ' ' .
            htmlentities($query['type'] ?? $query['typequery'] ?? '') . '</span>'
        ] = $query['forsql'] ?? $query['sql'] ?? '';

        $this->turnOffRetrieveText();

        $head = [_("No."), _("Type"), _("Value")];

        $options['thead'] = [
            ['style' => "text-align: left; width: 20px;"],
            ['style' => "text-align: left; width: 30px;"],
            ['style' => "text-align: left;"],
        ];

        $data = $this->argumentData(
            $query['sql'] ?? $query['forsql'] ?? '',
            $query['datatypes'] ?? [],
            $query['args'] ?? []
        )->table($head, $options);

        $this->setMessages($this->SQLBlock($message, $errorComponents, $data));

        parent::__construct($errorComponents, $code = 0, $file, $line, $previous);
    }

    /**
     * Parses the argument data and validates types against placeholders.
     * Supports sequential (%s, %d, %i) and positional (%1$s, %2$d, %3$i) placeholders.
     *
     * @param string $sql       The SQL query string.
     * @param array  $dataTypes The placeholder types ('s', 'd', 'i').
     * @param array  $args      The argument values.
     *
     * @return DatabaseException
     */
    private function argumentData(string $sql, array $dataTypes, array $args): self
    {
        // Extract all placeholders including positional: %s, %d, %i, %1$s, %2$d, %3$i
        preg_match_all('/(?<!%)%(?:\d+\$)?[sdi]/', $sql, $matches);
        $placeholders = $matches[0];

        if (count($placeholders) !== count($dataTypes)) {
            $this->errorArguments = '<div>' .
                _('Mismatched placeholder/datatype count. Found ') .
                count($placeholders) . _(' placeholders but ') .
                count($dataTypes) . _(' datatypes.') .
                '</div>';
        }

        foreach ($dataTypes as $index => $dataType) {
            $row = [];

            // Column 1: index number
            $row[] = $index;

            $placeholder = $placeholders[$index] ?? null;

            // Resolve human-readable type label
            switch ($dataType) {
                case 's':
                    $typeLabel = _('String');
                    break;
                case 'd':
                    $typeLabel = _('Integer');
                    break;
                case 'i':
                    $typeLabel = _('Identifier');
                    break;
                default:
                    $typeLabel = htmlspecialchars((string) $dataType);
            }

            // No placeholder found for this index
            if ($placeholder === null) {
                $row[] = '<span class="red">' . _('No placeholder found') . '</span>';
                $row[] = isset($args[$index])
                    ? htmlspecialchars((string) $args[$index])
                    : 'N/A';
                $this->data[] = $row;
                continue;
            }

            // No argument provided for this index
            if (!isset($args[$index])) {
                $row[] = '<span class="red">' . $typeLabel . ' — ' . _('missing argument') . '</span>';
                $row[] = '<span class="red">N/A</span>';
                $this->data[] = $row;
                continue;
            }

            $value = $args[$index];

            // Validate value type against placeholder type
            switch ($dataType) {
                case 'd':
                    $typeMatch = is_int($value) || (is_string($value) && is_numeric($value));
                    break;
                case 's':
                    $typeMatch = is_string($value) || is_null($value);
                    break;
                case 'i':
                    // Identifier must be a non-empty string
                    $typeMatch = is_string($value) && strlen(trim($value)) > 0;
                    break;
                default:
                    $typeMatch = false;
            }

            // Column 2: type label (red if mismatch)
            if ($typeMatch) {
                $row[] = $typeLabel;
            } else {
                $row[] = '<span class="red">' .
                    $typeLabel .
                    ' (' . _('expected') . ': ' . htmlspecialchars($dataType) .
                    ', ' . _('got') . ': ' . gettype($value) .
                    ')</span>';
            }

            // Column 3: value
            $row[] = is_null($value)
                ? '<em>NULL</em>'
                : htmlspecialchars((string) $value);

            $this->data[] = $row;
        }

        return $this;
    }

    /**
     * Constructs the SQL error block HTML.
     *
     * @param string $message The error message.
     * @param array  $sql     The SQL components to display.
     * @param string $data    The rendered argument table.
     *
     * @return array
     */
    private function SQLBlock(string $message, array $sql = [], string $data): array
    {
        $tmp = [];

        $tmp[] = '<div class="p" style="margin-top: 15px;">' . $message . '</div>';
        $tmp[] = '<div style="display: flex; column-gap: 15px;">';

        foreach ($sql as $t => $v) {
            $tmp[] = '
            <div class="code" style="margin-top: 15px; display: flex; align-content: flex-start; flex-wrap: wrap;">
                <div class="filname" style="width: 100%; justify-content: space-between;">' . $t . '</div>
                <div><pre><code class="language-sql">' . htmlentities($v) . '</code></pre></div>
            </div>';
        }

        $tmp[] = '
        <div class="code" style="margin-top: 15px; width: fit-content; overflow: initial;">
            <div class="filname" style="display: block;">' .
            _("SQL Query Arguments") . ' ' . $this->errorArguments .
            '</div>
            <div class="text">' . $data . '</div>
        </div></div>';

        $sd[0] = implode("\n", $tmp);

        return $sd;
    }

    /**
     * Renders the argument data as an HTML table.
     *
     * @param array $head    The table headers.
     * @param array $options The table options (e.g. thead styles).
     *
     * @return string
     */
    private function table(array $head, array $options): string
    {
        $html = '<table class="list">';

        if (!empty($head)) {
            $html .= '<thead><tr class="head">';
            foreach ($head as $index => $label) {
                $html .= '<td';
                if (isset($options['thead'][$index])) {
                    foreach ($options['thead'][$index] as $key => $value) {
                        $html .= ' ' . $key . '="' . $value . '"';
                    }
                }
                $html .= '>' . $label . '</td>';
            }
            $html .= '</tr></thead>';
        }

        if (!empty($this->data)) {
            $html .= '<tbody>';
            foreach ($this->data as $row) {
                $html .= '<tr>';
                foreach ($row as $cell) {
                    $html .= '<td>' . $cell . '</td>';
                }
                $html .= '</tr>';
            }
            $html .= '</tbody>';
        }

        $html .= '</table>';

        return $html;
    }
}