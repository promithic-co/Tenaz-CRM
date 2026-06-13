<?php

namespace App\Support;

class CsvDelimiterDetector
{
    /**
     * Detect the CSV delimiter from the first line of the handle.
     *
     * Brazilian Excel often uses semicolon. Semicolon wins ties. The handle is
     * always rewound to the start before returning.
     *
     * @param  resource  $handle
     */
    public static function detect($handle): string
    {
        $firstLine = fgets($handle);

        if ($firstLine === false) {
            rewind($handle);

            return ',';
        }

        rewind($handle);

        $semicolons = substr_count($firstLine, ';');
        $commas = substr_count($firstLine, ',');

        return $semicolons >= $commas ? ';' : ',';
    }
}
