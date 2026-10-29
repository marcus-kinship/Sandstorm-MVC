<?php

namespace Sys\Record\SQL;

/**
 * Class Parser
 * Parses SQL queries to extract table names and fields.
 * 
 * @category _("Parser class")
 */
class Parser
{
    private string $sql;

    /**
     * Constructor for Parser class.
     * 
     * @param string $sql The SQL query to be parsed.
     */
    public function __construct(string $sql = "")
    {
        $this->sql = $sql;
    }

    /**
     * Parses the SQL query to extract the table names.
     * 
     * @return array An array of table names.
     */
    public function getTableNames(){
        // Förbättrade regex-uttryck för att matcha tabellnamn efter FROM-klausulen med eller utan "AS"
        preg_match_all('/(?:from|FROM)\s+(?<tables>(?:\w+\s+)?(?:\w+\s+)?\w+(?:\s+AS\s+\w+)?(?:\s*,\s*(?:\w+\s+)?(?:\w+\s+)?\w+(?:\s+AS\s+\w+)?*)/', $this->sql, $matches);

        // Bearbeta matchningarna för att extrahera tabellnamnen
        $tableNames = [];
        foreach ($matches['tables'] as $tables) {
            $tables = preg_split('/\s*,\s*/', $tables);
            foreach ($tables as $table) {
                $tableNames[] = trim(preg_replace('/\s+AS\s+\w+/i', '', $table));
            }
        }

        return $tableNames;
    }

    /**
     * Parses the SQL query to extract the fields of a table.
     * 
     * @param string $tableName The name of the table to extract fields from.
     * @return array An array of fields for the specified table.
     */
    public function getFields(){
        $fields = [];

        // Regex mönster för att matcha fält i en SQL-query
        $pattern = '/(?<=select\s)(.*?)(?=\sfrom)/i';

        // Hämta SQL-frågan från klassens egenskap
        $sql = $this->sql;

        // Utför regex-matchning för att hitta fälten
        if (preg_match($pattern, $sql, $matches)) {
            // Splitta matchningen med kommatecken för att få individuella fältnamn
            $fields = preg_split('/,\s*/', $matches[0]);
        }

        return $fields;
    }
}
