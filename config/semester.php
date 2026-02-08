<?php
/**
 * Semester Helper Function
 * Calculates semester based on registration date
 */

/**
 * Calculate semester from a date
 * 01.03 - 30.09 = SoSe (Sommersemester)
 * 01.10 - 28/29.02 = WiSe (Wintersemester)
 * 
 * @param string|DateTime $date The registration date
 * @return string Semester code (e.g., "SoSe26", "WiSe2526")
 */
function calculateSemester($date) {
    if (is_string($date)) {
        $date = new DateTime($date);
    }
    
    $month = (int)$date->format('m');
    $year = (int)$date->format('y');
    $fullYear = (int)$date->format('Y');
    
    // March (03) to September (09) = Summer semester (SoSe{YY})
    if ($month >= 3 && $month <= 9) {
        return "SoSe" . $year;
    }
    // October (10) to February (02) = Winter semester (WiSe{YY}{YY+1})
    // October onwards: WiSe{CurrentYear}{NextYear}
    // January/February: WiSe{PreviousYear}{CurrentYear}
    else {
        if ($month >= 10) {
            // October onwards - belongs to WiSe starting this year
            $nextYear = $year + 1;
            return "WiSe" . $year . $nextYear;
        } else {
            // January/February - belongs to WiSe starting previous year
            $prevYear = $year - 1;
            return "WiSe" . $prevYear . $year;
        }
    }
}

/**
 * Get current semester
 * @return string Current semester code
 */
function getCurrentSemester() {
    return calculateSemester(new DateTime());
}

/**
 * Verify semester format
 * @param string $semester Semester string
 * @return bool True if valid semester format
 */
function isValidSemester($semester) {
    // Match SoSe## or WiSe####
    return preg_match('/^(SoSe|WiSe)\d{2,4}$/', $semester) === 1;
}

// Example usage:
// echo calculateSemester('2026-03-15'); // Output: SoSe26
// echo calculateSemester('2026-10-01'); // Output: WiSe2627
// echo getCurrentSemester();
