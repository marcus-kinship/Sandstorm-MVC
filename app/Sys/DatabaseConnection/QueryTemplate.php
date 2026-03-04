<?php
namespace Sys\DatabaseConnection;

/**
 * QueryTemplate - Compiles and analyzes SQL query templates.
 * Extracts placeholder information and applies pattern protection.
 * Results are cached via QueryCache for reuse.
 */
class QueryTemplate
{
    private ProtectedPatterns $patterns;

    public function __construct(ProtectedPatterns $patterns)
    {
        $this->patterns = $patterns;
    }

    /**
     * Compile a query template and return precompiled placeholder information.
     * Result is cached — subsequent calls with the same template are instant.
     *
     * @param string $queryString SQL query template
     * @return array Compiled template data
     */
    public function compile(string $queryString): array
    {
        $cacheKey = md5($queryString . $this->patterns->fingerprint());

        $cached = QueryCache::get($cacheKey);
        if ($cached !== null) {
            return $cached;
        }

        // STEP 0: Auto-wrap bare %s and positional %N$s with single quotes
        $queryString = preg_replace(
            '/(?<![\'"`])(%(?:\d+\$)?s)(?![\'"`])/',
            "'$1'",
            $queryString
        );

        // STEP 1: Protect %%s%, %%d%, %%i% and positional variants from sprintf
        $hasDoublePercent = false;
        if (preg_match('/%%(?:\d+\$)?[sdi]%/', $queryString)) {
            $queryString = preg_replace(
                ['/%%(\d+\$)?s%/', '/%%(\d+\$)?d%/', '/%%(\d+\$)?i%/'],
                ['__¤__${1}s__¤__', '__¤__${1}d__¤__', '__¤__${1}i__¤__'],
                $queryString
            );
            $hasDoublePercent = true;
        }

        // STEP 2: Apply protected patterns
        $patternResult = $this->patterns->apply($queryString);
        $queryString = $patternResult['query'];

        // STEP 3: Analyze placeholders — supports sequential and positional
        preg_match_all('/%(\d+\$)?([sdi])/', $queryString, $matches, PREG_OFFSET_CAPTURE);
        $placeholders = [];
        $sequentialIndex = 0;

        foreach ($matches[0] as $index => $match) {
            $positionStr = $matches[1][$index][0]; // e.g. "2$" or ""
            $type = $matches[2][$index][0]; // s, d, or i

            $argIndex = ($positionStr !== '')
                ? (int) $positionStr - 1
                : $sequentialIndex++;

            $placeholders[] = [
                'type' => $type,
                'position' => $match[1],
                'index' => $argIndex,
                'positional' => $positionStr !== ''
            ];
        }

        // STEP 4: Cache and return
        $templateData = [
            'processed_query' => $queryString,
            'placeholders' => $placeholders,
            'placeholder_count' => count($placeholders),
            'has_double_percent' => $hasDoublePercent,
            'protected_replacements_template' => $patternResult['replacements'],
            'has_protected' => !empty($patternResult['replacements'])
        ];

        QueryCache::set($cacheKey, $templateData);

        return $templateData;
    }
}