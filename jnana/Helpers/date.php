<?php
if (!function_exists('php_to_js_date_format')) {
    function php_to_js_date_format(string $format): string
    {
        // Map PHP date() tokens => jQuery UI datepicker tokens
        $map = [
            // Day
            'd' => 'dd',  // 01-31 -> dd
            'j' => 'd',   // 1-31  -> d
            'D' => 'D',   // Mon   -> D
            'l' => 'DD',  // Monday -> DD
            'z' => 'o',   // 0-365 -> day of year (001–365)

            // Month
            'm' => 'mm',  // 01-12 -> mm
            'n' => 'm',   // 1-12  -> m
            'M' => 'M',   // Jan   -> M
            'F' => 'MM',  // January -> MM

            // Year
            'Y' => 'yy',  // 2025 -> yy
            'y' => 'y',   // 25   -> y
        ];

        // Simple character-based replacement
        return strtr($format, $map);
    }

}

if (!function_exists('normalizeDate')) {
    function normalizeDate(
        ?string $date,
        string $outputFormat = 'Y-m-d'
    ): ?string {
        if (empty($date)) {
            return null;
        }

        // Already normalized
        if (
            $outputFormat === 'Y-m-d' &&
            preg_match('/^\d{4}-\d{2}-\d{2}$/', $date)
        ) {
            return $date;
        }

        $inputFormat = defaultDateFormat();

        $dateTime = \DateTime::createFromFormat(
            $inputFormat,
            trim($date)
        );

        if (!$dateTime) {
            throw new \RuntimeException(
                sprintf(
                    'Invalid date "%s". Expected format: %s',
                    $date,
                    $inputFormat
                )
            );
        }

        return $dateTime->format($outputFormat);
    }

}




