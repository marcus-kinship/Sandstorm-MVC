/**
 * Konverterar storlek från byte till läsbar formatering (kb, mb, gb, etc.).
 *
 * @param int $size Storleken som ska konverteras, angiven i byte.
 *
 * @return string En sträng som representerar storleken i läsbar form med enhet (b, kb, mb, gb, etc.).
 */
function convert($size) {
    $unit = array('b', 'kb', 'mb', 'gb', 'tb', 'pb');

    // Räkna ut vilken enhet som ska användas baserat på storleken
    $i = floor(log($size, 1024));

    // Beräkna den konverterade storleken och avrunda till två decimaler
    $convertedSize = round($size / pow(1024, $i), 2);

    // Returnera den konverterade storleken med enhet
    return $convertedSize . ' ' . $unit[$i];
}
